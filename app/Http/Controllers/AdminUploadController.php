<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\UploadSession;
use App\Services\ChunkCipher;
use App\Services\PostfileClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Chunked upload so big PDFs get past Vercel's 4.5 MB request limit:
 *   1) init   -> new upload session (own random key + nonce prefix)
 *   2) piece  -> browser sends 3 MB pieces; each is encrypted on arrival and staged in the database
 *   3) finish -> pieces are joined in order into one encrypted file, sent to Postfile, book is created
 */
class AdminUploadController extends Controller
{
    private const MAX_BYTES = 50 * 1024 * 1024; // Postfile free-plan limit

    public function init(Request $r, ChunkCipher $cipher)
    {
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:' . self::MAX_BYTES,
        ]);

        // forget abandoned uploads older than a day (pieces go with them)
        $old = UploadSession::where('created_at', '<', now()->subDay())->pluck('id');
        if ($old->isNotEmpty()) {
            DB::table('upload_pieces')->whereIn('upload_id', $old)->delete();
            UploadSession::whereIn('id', $old)->delete();
        }

        $piece = (int) config('library.upload_piece');
        $session = UploadSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $r->user()->id,
            'file_name' => $d['name'],
            'plain_size' => $d['size'],
            'piece_size' => $piece,
            'pieces' => (int) ceil($d['size'] / $piece),
            'nonce_prefix' => base64_encode(random_bytes(8)),
            'key_enc' => $cipher->wrap($cipher->newKey()),
        ]);

        return response()->json(['id' => $session->id, 'piece_size' => $piece, 'pieces' => $session->pieces]);
    }

    public function piece(Request $r, string $upload, int $index, ChunkCipher $cipher)
    {
        $s = UploadSession::where('id', $upload)->where('user_id', $r->user()->id)->firstOrFail();
        abort_unless($index >= 0 && $index < $s->pieces, 422, 'Bad piece number.');

        $body = $r->getContent();
        $expected = $index === $s->pieces - 1 ? $s->plain_size - $index * $s->piece_size : $s->piece_size;
        abort_unless(strlen($body) === $expected, 422, "Piece {$index} has the wrong size, please retry.");
        abort_if($index === 0 && ! str_starts_with($body, '%PDF-'), 422, 'That file is not a PDF.');

        $chunk = (int) config('library.chunk_size');
        $encrypted = $cipher->encryptPiece(
            $body,
            $cipher->unwrap($s->key_enc),
            base64_decode($s->nonce_prefix),
            intdiv($index * $s->piece_size, $chunk),
            $chunk
        );

        // updateOrInsert = a retried piece simply replaces the earlier attempt
        DB::table('upload_pieces')->updateOrInsert(
            ['upload_id' => $s->id, 'idx' => $index],
            ['data' => base64_encode($encrypted)]
        );

        return response()->json(['ok' => true]);
    }

    public function finish(Request $r, string $upload, ChunkCipher $cipher, PostfileClient $postfile)
    {
        @set_time_limit(0);

        $s = UploadSession::where('id', $upload)->where('user_id', $r->user()->id)->firstOrFail();
        $d = $r->validate([
            'title' => 'required|string|max:200',
            'author' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:5000',
            'pages' => 'nullable|integer|min:1|max:100000',
            'cover_data' => ['nullable', 'string', 'max:400000', 'regex:/^data:image\/(webp|jpeg|png);base64,[A-Za-z0-9+\/=]+$/'],
        ]);

        $have = DB::table('upload_pieces')->where('upload_id', $s->id)->count();
        if ($have !== (int) $s->pieces) {
            return response()->json(['message' => "Only {$have} of {$s->pieces} pieces arrived. Please upload again."], 422);
        }

        // join header + encrypted pieces (in order) into one temp file
        $chunk = (int) config('library.chunk_size');
        $tmp = tempnam(sys_get_temp_dir(), 'lib');
        $out = fopen($tmp, 'wb');
        fwrite($out, $cipher->header(base64_decode($s->nonce_prefix), $chunk));
        for ($i = 0; $i < $s->pieces; $i++) {
            $row = DB::table('upload_pieces')->where('upload_id', $s->id)->where('idx', $i)->value('data');
            fwrite($out, base64_decode((string) $row));
        }
        fclose($out);

        $encSize = ChunkCipher::HEADER + (int) ceil($s->plain_size / $chunk) * ChunkCipher::TAG + $s->plain_size;
        if (filesize($tmp) !== $encSize) {
            @unlink($tmp);
            return response()->json(['message' => 'The assembled file has the wrong size. Please upload again.'], 422);
        }

        try {
            $up = $postfile->upload($tmp, Str::uuid() . '.bin');
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage() . ' (your pieces are kept, press the button to retry)'], 502);
        } finally {
            @unlink($tmp);
        }

        try {
            Book::create([
                'title' => $d['title'],
                'author' => $d['author'] ?? null,
                'category' => $d['category'] ?? null,
                'description' => $d['description'] ?? null,
                'pages' => $d['pages'] ?? null,
                'cover_data' => $d['cover_data'] ?? null,
                'postfile_id' => $up['file_id'],
                'postfile_url' => $up['url'],
                'plain_size' => $s->plain_size,
                'enc_size' => $encSize,
                'chunk_size' => $chunk,
                'nonce_prefix' => $s->nonce_prefix,
                'key_enc' => $s->key_enc,
                'is_published' => $r->boolean('is_published'),
            ]);
        } catch (\Throwable $e) {
            try { $postfile->delete($up['file_id']); } catch (\Throwable) {}
            throw $e;
        }

        DB::table('upload_pieces')->where('upload_id', $s->id)->delete();
        $s->delete();
        session()->flash('status', 'Book added.');

        return response()->json(['redirect' => route('admin.books.index')]);
    }
}
