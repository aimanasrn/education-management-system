<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\BcryptPasswordLength;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin {email} {--name=School Administrator}')]
#[Description('Create an administrator with a securely prompted password')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $identity = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($identity->fails()) {
            $this->error($identity->errors()->first());

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter a password securely.');

            return self::FAILURE;
        }

        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');
        $credentials = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], ['password' => ['required', 'string', 'confirmed', new BcryptPasswordLength, Password::min(12)]]);

        if ($credentials->fails()) {
            $this->error($credentials->errors()->first());

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = UserRole::Admin;
        $user->save();

        $this->info('Administrator created: '.$email);

        return self::SUCCESS;
    }
}
