<?php

namespace App\Console\Commands;

use App\Models\UserManagement\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('users:create
    {--admin : Make the user a platform administrator}
    {--name= : Full name}
    {--email= : Email address}
    {--phone= : Phone number, e.g. 0712345678 or +255712345678}
    {--password= : Password (prompted for when omitted)}
    {--unverified : Do not mark the email as verified}')]
#[Description('Create a user (or a platform administrator with --admin) who can sign in straight away')]
class CreateUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isInteractive = $this->input->isInteractive();

        $name = $this->option('name') ?? ($isInteractive ? text('Full name', required: true) : null);
        $email = $this->option('email') ?? ($isInteractive ? text('Email address', required: true) : null);
        $phone = $this->option('phone') ?? ($isInteractive ? text('Phone number', required: true) : null);
        $secret = $this->option('password') ?? ($isInteractive ? password('Password', required: true) : null);

        $input = [
            'name' => $name,
            'email' => Str::lower(trim((string) $email)),
            'phone_number' => User::normalizePhone((string) $phone),
            'password' => $secret,
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^\+?[0-9]{9,15}$/', 'unique:users,phone_number'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = new User($validator->validated());
        $user->forceFill([
            'is_active' => true,
            'status' => 'active',
            'is_platform_admin' => $this->option('admin'),
            'email_verified_at' => $this->option('unverified') ? null : now(),
        ])->save();

        $role = $user->is_platform_admin ? 'Platform administrator' : 'User';
        $this->components->info("{$role} {$user->name} created. They can sign in with {$user->email} or {$user->phone_number}.");

        return self::SUCCESS;
    }
}
