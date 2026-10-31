<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature = 'library:make-admin {email} {--name=Admin}';
    protected $description = 'Create a user (or promote an existing one) to admin';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $pw = (string) $this->secret('Password for the new admin (min 8 characters)');
            if (strlen($pw) < 8) {
                $this->error('Password too short.');
                return self::FAILURE;
            }
            $user = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => Hash::make($pw)]);
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("{$email} is now an admin.");

        return self::SUCCESS;
    }
}
