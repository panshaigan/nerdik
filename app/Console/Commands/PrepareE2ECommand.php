<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\E2E\PrepareE2EFixtures;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('e2e:prepare {--fresh : Wipe the database before seeding fixtures} {--json : Print fixture JSON to stdout}')]
#[Description('Upsert deterministic users, event, activity, and place used by Playwright browser tests')]
final class PrepareE2ECommand extends Command
{
    public function handle(PrepareE2EFixtures $prepare): int
    {
        if ((bool) $this->option('fresh')) {
            if (app()->isProduction()) {
                $this->error('Refusing --fresh in production.');

                return self::FAILURE;
            }

            $this->call('migrate:fresh', ['--force' => true]);
        }

        $payload = $prepare();

        $this->info('Wrote Playwright fixtures to '.PrepareE2EFixtures::fixturePath());

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
