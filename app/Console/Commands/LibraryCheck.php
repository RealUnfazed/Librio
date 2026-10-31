<?php

namespace App\Console\Commands;

use App\Services\ChunkCipher;
use App\Services\PostfileClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LibraryCheck extends Command
{
    protected $signature = 'library:check {--upload : Do a real tiny test upload (uses 1 of your monthly Postfile uploads)}';
    protected $description = 'Check master key, Postfile API key, and (with --upload) CORS / Range / file-type support';

    public function handle(ChunkCipher $cipher, PostfileClient $pf): int
    {
        try {
            $cipher->unwrap($cipher->wrap('test'));
            $this->info('OK  master key is valid');
        } catch (\Throwable $e) {
            $this->error('FAIL master key: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $a = $pf->account();
            $this->info(sprintf('OK  Postfile key works (plan %s, %s/%s uploads this month, max file %s MB)',
                $a['plan'] ?? '?', $a['uploads_this_month'] ?? '?', $a['upload_limit'] ?? '?', $a['max_file_size_mb'] ?? '?'));
        } catch (\Throwable $e) {
            $this->error('FAIL Postfile: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('Delivery mode: ' . config('library.delivery') . ' | registration: ' . config('library.registration'));

        if (! $this->option('upload')) {
            $this->comment('Run with --upload to test CORS and Range support (needed for the "encrypted" mode on Vercel).');
            return self::SUCCESS;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($tmp, random_bytes(2048));
        $up = null;

        try {
            $up = $pf->upload($tmp, 'library-check.bin');
            $this->info('OK  .bin upload accepted');

            $origin = rtrim((string) config('app.url'), '/');
            $head = Http::withHeaders(['Origin' => $origin])->timeout(30)->get($up['url']);
            $cors = $head->header('Access-Control-Allow-Origin');
            $this->line($cors
                ? "OK  CORS header present: {$cors} (Origin sent: {$origin})"
                : 'FAIL no Access-Control-Allow-Origin header: browsers on another domain CANNOT download the file. Use LIBRARY_DELIVERY=proxy or another host.');

            $range = Http::withHeaders(['Range' => 'bytes=0-15'])->timeout(30)->get($up['url']);
            $this->line($range->status() === 206
                ? 'OK  Range requests supported (206)'
                : 'NOTE Range not honoured (HTTP ' . $range->status() . '): proxy mode still works but downloads whole file each time.');
        } catch (\Throwable $e) {
            $this->error('FAIL test upload: ' . $e->getMessage());
        } finally {
            @unlink($tmp);
            if ($up) {
                try { $pf->delete($up['file_id']); $this->line('Test file deleted.'); } catch (\Throwable) {}
            }
        }

        return self::SUCCESS;
    }
}
