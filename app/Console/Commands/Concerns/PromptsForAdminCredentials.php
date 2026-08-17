<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

trait PromptsForAdminCredentials
{
    /**
     * @return array{email: string, nickname: string, password: string}|null
     */
    protected function adminCredentialsFromInput(): ?array
    {
        $email = $this->stringOptionOrAsk('email', 'Admin email');

        if ($email === null || $email === '') {
            $this->error('Email is required.');

            return null;
        }

        $email = Str::lower(trim($email));

        try {
            $email = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class]],
            )->validate()['email'];
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first('email') ?? 'Invalid email.');

            return null;
        }

        $nickname = $this->stringOptionOrAsk(
            'nickname',
            'Admin nickname',
            User::generateUniqueNicknameFromEmail($email),
        );

        if ($nickname === null || $nickname === '') {
            $this->error('Nickname is required.');

            return null;
        }

        try {
            $nickname = Validator::make(
                ['nickname' => $nickname],
                ['nickname' => ['required', 'string', 'max:255', 'unique:'.User::class]],
            )->validate()['nickname'];
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first('nickname') ?? 'Invalid nickname.');

            return null;
        }

        $password = $this->optionalStringOption('password');

        if ($password === null) {
            $password = (string) $this->secret('Admin password');
            $confirmation = (string) $this->secret('Confirm password');

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');

                return null;
            }
        }

        try {
            $password = Validator::make(
                ['password' => $password],
                ['password' => ['required', 'string', Password::defaults()]],
            )->validate()['password'];
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first('password') ?? 'Invalid password.');

            return null;
        }

        return [
            'email' => $email,
            'nickname' => $nickname,
            'password' => $password,
        ];
    }

    protected function optionalStringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    protected function stringOptionOrAsk(string $option, string $question, ?string $default = null): ?string
    {
        $value = $this->optionalStringOption($option);

        if ($value !== null) {
            return $value;
        }

        $answer = $this->ask($question, $default);

        return is_string($answer) ? $answer : null;
    }
}
