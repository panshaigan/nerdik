<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Users\CreateAdminUser;
use App\Console\Commands\Concerns\PromptsForAdminCredentials;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:create-admin {--email= : Admin email} {--nickname= : Admin nickname} {--password= : Admin password}')]
#[Description('Create a verified admin account without wiping the database')]
final class CreateAdminUserCommand extends Command
{
    use PromptsForAdminCredentials;

    public function handle(CreateAdminUser $createAdmin): int
    {
        $credentials = $this->adminCredentialsFromInput();

        if ($credentials === null) {
            return self::FAILURE;
        }

        $user = $createAdmin(
            email: $credentials['email'],
            password: $credentials['password'],
            nickname: $credentials['nickname'],
        );

        $this->info("Admin account created for {$user->email} ({$user->nickname}).");

        return self::SUCCESS;
    }
}
