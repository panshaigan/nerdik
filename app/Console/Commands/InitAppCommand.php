<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\App\BootstrapProductionState;
use App\Actions\Users\CreateAdminUser;
use App\Console\Commands\Concerns\PromptsForAdminCredentials;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:init {--force : Required in production, and when running non-interactively} {--email= : Admin email} {--nickname= : Admin nickname} {--password= : Admin password}')]
#[Description('Wipe the database, seed base production data, regenerate tag images, and create the first admin')]
final class InitAppCommand extends Command
{
    use PromptsForAdminCredentials;

    public function handle(BootstrapProductionState $bootstrap, CreateAdminUser $createAdmin): int
    {
        if (app()->isProduction() && ! (bool) $this->option('force')) {
            $this->error('The --force option is required in production.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive() && ! (bool) $this->option('force')) {
            $this->error('The --force option is required when running non-interactively.');

            return self::FAILURE;
        }

        if (app()->isProduction()) {
            $this->warn('Consider running `make backup-prod` before continuing.');
        }

        if ($this->input->isInteractive()
            && ! $this->confirm('This will drop all tables and leftover media files. Continue?', false)
        ) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $credentials = $this->adminCredentialsFromInput();

        if ($credentials === null) {
            return self::FAILURE;
        }

        $bootstrap($this);

        $user = $createAdmin(
            email: $credentials['email'],
            password: $credentials['password'],
            nickname: $credentials['nickname'],
        );

        $this->info("Admin account created for {$user->email} ({$user->nickname}).");

        return self::SUCCESS;
    }
}
