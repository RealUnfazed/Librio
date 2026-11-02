<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LibraryDoctor
{
    /** @return array<int, array{ok: ?bool, text: string}>  ok = true (good), false (broken), null (note) */
    public function run(bool $upload = false): array
    {
        $out = [];
        $add = function (?bool $ok, string $text) use (&$out) {
            $out[] = ['ok' => $ok, 'text' => $text];
        };

        $add((string) config('app.key') !== '', (string) config('app.key') !== '' ? 'APP_KEY is set' : 'APP_KEY is missing');

        try {
            DB::connection()->getPdo();
            $add(true, 'Database connected (' . config('database.default') . ')');
            $need = ['users', 'books', 'invites', 'bookmarks', 'reading_progress', 'cache', 'upload_sessions', 'upload_pieces', 'categories', 'book_category'];
            $missing = array_values(array_filter($need, fn ($t) => ! Schema::hasTable($t)));
            $add(! $missing, $missing
                ? 'Tables missing: ' . implode(', ', $missing) . '. Set LIBRARY_AUTO_SETUP=true and redeploy.'
                : 'All tables exist');
        } catch (\Throwable $e) {
            $add(false, 'Database not reachable. Check DB_URL. (' . class_basename($e) . ')');
        }

        try {
            $cipher = app(ChunkCipher::class);
            $cipher->unwrap($cipher->wrap('x'));
            $add(true, 'LIBRARY_MASTER_KEY is valid');
        } catch (\Throwable $e) {
            $add(false, $e->getMessage());
        }

        $postfile = app(PostfileClient::class);
        try {
            $a = $postfile->account();
            $add(true, sprintf('Postfile API key works (plan %s, %s/%s uploads this month)',
                $a['plan'] ?? '?', $a['uploads_this_month'] ?? '?', $a['upload_limit'] ?? '?'));
        } catch (\Throwable $e) {
            $add(false, 'Postfile: ' . $e->getMessage());
        }

        $add(null, 'Delivery: ' . config('library.delivery') . ' | registration: ' . config('library.registration') . ' | APP_URL: ' . config('app.url'));

        if (! $upload) {
            return $out;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($tmp, random_bytes(2048));
        $up = null;
        try {
            $up = $postfile->upload($tmp, 'library-check.bin');
            $add(true, 'Postfile accepted a .bin test upload');

            $origin = rtrim((string) config('app.url'), '/');
            $get = Http::withHeaders(['Origin' => $origin])->timeout(30)->get($up['url']);
            $cors = $get->header('Access-Control-Allow-Origin');
            $proxy = config('library.delivery') === 'proxy';
            $add($cors ? true : ($proxy ? null : false), $cors
                ? "The file host allows browser downloads (CORS: {$cors})"
                : ($proxy
                    ? 'The file host sends no CORS header. That is fine: Librio streams books through your server (proxy mode).'
                    : 'The file host sends NO CORS header, so LIBRARY_DELIVERY=encrypted cannot work. Set LIBRARY_DELIVERY=proxy.'));

            $range = Http::withHeaders(['Range' => 'bytes=0-15'])->timeout(30)->get($up['url']);
            $add($range->status() === 206 ? true : null, $range->status() === 206
                ? 'The file host supports Range requests (fast page-by-page streaming)'
                : 'The file host ignores Range requests (HTTP ' . $range->status() . '): books still open, but more slowly.');
        } catch (\Throwable $e) {
            $add(false, 'Upload test failed: ' . $e->getMessage());
        } finally {
            @unlink($tmp);
            if ($up) {
                try { $postfile->delete($up['file_id']); } catch (\Throwable) {}
            }
        }

        return $out;
    }
}
