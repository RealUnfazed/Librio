<?php

namespace App\Console\Commands;

use App\Services\LibraryDoctor;
use Illuminate\Console\Command;

class LibraryCheck extends Command
{
    protected $signature = 'library:check {--upload : Also do a real tiny test upload (uses 1 Postfile upload)}';
    protected $description = 'Check keys, database, Postfile, and (with --upload) CORS / Range support';

    public function handle(LibraryDoctor $doctor): int
    {
        $failed = false;
        foreach ($doctor->run((bool) $this->option('upload')) as $r) {
            $tag = $r['ok'] === true ? 'OK   ' : ($r['ok'] === false ? 'FAIL ' : 'NOTE ');
            $this->line($tag . $r['text']);
            $failed = $failed || $r['ok'] === false;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
