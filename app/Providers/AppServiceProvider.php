<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('library.force_https')) {
            URL::forceScheme('https');
        }

        if (config('library.auto_setup') && ! $this->app->runningInConsole()) {
            $this->autoSetup();
        }
    }

    /** First visit on Vercel: create tables, then the first admin from LIBRARY_ADMIN_EMAIL / _PASSWORD. */
    private function autoSetup(): void
    {
        $flag = sys_get_temp_dir() . '/library-ready-' . md5((string) config('app.key') . config('database.default'));
        if (is_file($flag)) {
            return;
        }

        try {
            if (! Schema::hasTable('books')) {
                Artisan::call('migrate', ['--force' => true]);
            }

            $email = config('library.admin.email');
            $password = (string) config('library.admin.password');

            if ($email && strlen($password) >= 8 && ! User::where('is_admin', true)->exists()) {
                $user = User::where('email', $email)->first()
                    ?? new User(['name' => 'Admin', 'email' => $email, 'password' => Hash::make($password)]);
                $user->forceFill(['is_admin' => true])->save();
            }

            @file_put_contents($flag, '1');
        } catch (\Throwable $e) {
            report($e);   // goes to the Vercel logs; the request then shows the readable setup message
        }
    }
}
