<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Signature('aquaflow:bootstrap-admin {email} {name}')]
#[Description('Create the initial AquaFlow Super Admin')]
class BootstrapSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::where('is_super_admin', true)->exists()) {
            $this->error('A Super Admin already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Str::random(64),
            'is_super_admin' => true,
        ]);

        Password::sendResetLink(['email' => $user->email]);
        $user->sendEmailVerificationNotification();
        $this->info('Super Admin created. Password reset and verification messages were sent.');

        return self::SUCCESS;
    }
}
