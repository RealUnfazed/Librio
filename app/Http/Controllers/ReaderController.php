<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Bookmark;
use App\Models\ReadingProgress;
use App\Services\ChunkCipher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReaderController extends Controller
{
    private function authorizeBook(Request $r, Book $book): void
    {
        abort_unless($book->is_published || $r->user()->is_admin, 404);
    }

    /** Short-lived HMAC token so the stream URL only works for this user + book (no dependence on proxy headers). */
    private function token(int $bookId, int $userId, int $exp): string
    {
        return hash_hmac('sha256', "stream|{$bookId}|{$userId}|{$exp}", (string) config('app.key'));
    }

    public function read(Request $r, Book $book)
    {
        $this->authorizeBook($r, $book);

        $cfg = [
            'manifestUrl' => route('books.manifest', $book),
            'progressUrl' => route('books.progress', $book),
            'bookmarkUrl' => route('books.bookmark', $book),
            'workerSrc' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
            'csrf' => csrf_token(),
        ];

        return view('library.reader', compact('book', 'cfg'));
    }

    public function manifest(Request $r, Book $book, ChunkCipher $cipher)
    {
        $this->authorizeBook($r, $book);
        $user = $r->user();

        $data = [
            'mode' => config('library.delivery'),
            'plain_size' => $book->plain_size,
            'chunk_size' => $book->chunk_size,
            'pages' => $book->pages,
            'progress' => (int) (ReadingProgress::where(['user_id' => $user->id, 'book_id' => $book->id])->value('page') ?? 1),
            'bookmarks' => Bookmark::where(['user_id' => $user->id, 'book_id' => $book->id])->orderBy('page')->pluck('page'),
            'watermark' => $user->email . ' · #' . $user->id,
        ];

        if (config('library.delivery') === 'proxy') {
            $exp = time() + 3600;
            $data['url'] = route('books.stream', [
                'book' => $book->id,
                'e' => $exp,
                'u' => $user->id,
                't' => $this->token($book->id, $user->id, $exp),
            ]);
        } else {
            $data['url'] = $book->postfile_url;
            $data['enc_size'] = $book->enc_size;
            $data['nonce_prefix'] = $book->nonce_prefix;
            $data['key'] = base64_encode($cipher->unwrap($book->key_enc));
        }

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    /** Proxy delivery: decrypts on the fly, supports HTTP Range so PDF.js can stream. */
    public function stream(Request $r, Book $book, ChunkCipher $cipher): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless(config('library.delivery') === 'proxy', 404);
        $this->authorizeBook($r, $book);

        $exp = (int) $r->query('e');
        abort_unless(
            $exp > time()
            && (int) $r->query('u') === $r->user()->id
            && hash_equals($this->token($book->id, $r->user()->id, $exp), (string) $r->query('t')),
            403
        );
        // Direct navigation / <iframe> / <embed> to this URL is refused; PDF.js fetches via XHR/fetch.
        abort_if(in_array($r->header('Sec-Fetch-Dest'), ['document', 'iframe', 'frame', 'embed', 'object'], true), 403);

        $size = $book->plain_size;
        $cs = $book->chunk_size;
        $start = 0;
        $end = $size - 1;
        $status = 200;

        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) $r->header('Range', ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min($end, (int) $m[2]);
                }
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $status = 206;
        }

        $c0 = intdiv($start, $cs);
        $c1 = intdiv($end, $cs);
        $last = intdiv($size - 1, $cs);
        $block = $cs + ChunkCipher::TAG;
        $encStart = ChunkCipher::HEADER + $c0 * $block;
        $encEnd = min($book->enc_size - 1, ChunkCipher::HEADER + ($c1 + 1) * $block - 1);

        $up = \Illuminate\Support\Facades\Http::withHeaders(['Range' => "bytes={$encStart}-{$encEnd}"])
            ->withOptions(['stream' => true])->timeout(120)->get($book->postfile_url);
        abort_unless(in_array($up->status(), [200, 206], true), 502);
        $body = $up->toPsrResponse()->getBody();
        if ($up->status() === 200) {          // CDN ignored Range: skip ahead ourselves
            ChunkCipher::discard($body, $encStart);
        }

        $key = $cipher->unwrap($book->key_enc);
        $prefix = base64_decode($book->nonce_prefix);

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Length' => $end - $start + 1,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="book.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($body, $cipher, $key, $prefix, $c0, $c1, $last, $cs, $size, $start, $end) {
            $skip = $start - $c0 * $cs;
            $remaining = $end - $start + 1;
            for ($i = $c0; $i <= $c1 && $remaining > 0; $i++) {
                $plainLen = $i === $last ? $size - $i * $cs : $cs;
                $raw = ChunkCipher::readExactly($body, $plainLen + ChunkCipher::TAG);
                if (strlen($raw) !== $plainLen + ChunkCipher::TAG) {
                    break;
                }
                $plain = $cipher->decryptChunk($raw, $key, $prefix, $i);
                if ($skip > 0) {
                    $plain = substr($plain, $skip);
                    $skip = 0;
                }
                if (strlen($plain) > $remaining) {
                    $plain = substr($plain, 0, $remaining);
                }
                echo $plain;
                $remaining -= strlen($plain);
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
        }, $status, $headers);
    }

    public function progress(Request $r, Book $book)
    {
        $this->authorizeBook($r, $book);
        $page = $r->validate(['page' => 'required|integer|min:1|max:100000'])['page'];

        ReadingProgress::updateOrCreate(
            ['user_id' => $r->user()->id, 'book_id' => $book->id],
            ['page' => $page]
        );

        return response()->noContent();
    }

    public function bookmark(Request $r, Book $book)
    {
        $this->authorizeBook($r, $book);
        $page = $r->validate(['page' => 'required|integer|min:1|max:100000'])['page'];
        $keys = ['user_id' => $r->user()->id, 'book_id' => $book->id];

        $existing = Bookmark::where($keys)->where('page', $page)->first();
        $existing ? $existing->delete() : Bookmark::create($keys + ['page' => $page]);

        return response()->json(Bookmark::where($keys)->orderBy('page')->pluck('page'));
    }
}
