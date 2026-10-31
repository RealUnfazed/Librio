<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LibraryInstall extends Command
{
    protected $signature = 'library:install {--force : Replace an existing master key (existing books become UNREADABLE)}';
    protected $description = 'Generate LIBRARY_MASTER_KEY in .env (if missing) and run the migrations';

    public function handle(): int
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->error('.env not found. Copy .env.example to .env first.');
            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        $hasKey = (bool) preg_match('/^LIBRARY_MASTER_KEY=\S+/m', $env);

        if (! $hasKey || $this->option('force')) {
            $key = base64_encode(random_bytes(32));
            $env = preg_match('/^LIBRARY_MASTER_KEY=.*$/m', $env)
                ? preg_replace('/^LIBRARY_MASTER_KEY=.*$/m', 'LIBRARY_MASTER_KEY=' . $key, $env)
                : rtrim($env) . "\nLIBRARY_MASTER_KEY=" . $key . "\n";
            file_put_contents($path, $env);
            $this->warn('LIBRARY_MASTER_KEY=' . $key);
            $this->warn('Back this up and put the SAME value in Vercel. If you lose it, every book is unreadable.');
        } else {
            $this->info('Master key already set, keeping it.');
        }

        return $this->call('migrate', ['--force' => true]);
    }
}
