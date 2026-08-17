<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class CreateAdminUser
{
    /**
     * @throws ValidationException
     */
    public function __invoke(string $email, string $password, string $nickname, ?string $name = null): User
    {
        $validated = Validator::make(
            [
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'nickname' => trim($nickname),
                'name' => $name,
            ],
            [
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', Password::defaults()],
                'nickname' => ['required', 'string', 'max:255', 'unique:'.User::class],
                'name' => ['nullable', 'string', 'max:255'],
            ],
        )->validate();

        $user = User::query()->create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'nickname' => $validated['nickname'],
            'name' => $validated['name'] ?? null,
        ]);

        $user->is_admin = true;
        $user->email_verified_at = now();
        $user->save();

        $user->profile()->firstOrCreate();

        return $user->refresh()->load('profile');
    }
}
