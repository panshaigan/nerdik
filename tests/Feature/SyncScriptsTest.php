<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class SyncScriptsTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function syncScripts(): array
    {
        return [
            'scripts/sync/common.sh',
            'scripts/sync/export-from-env.sh',
            'scripts/sync/import-to-env.sh',
            'scripts/sync/pull-from-prod.sh',
            'scripts/sync/prod-to-staging.sh',
            'scripts/sync/prod-to-staging-remote.sh',
            'scripts/sync/sync-from-prod.sh',
            'scripts/sync/sync-to-staging.sh',
        ];
    }

    public function test_sync_scripts_exist_and_are_executable(): void
    {
        foreach ($this->syncScripts() as $script) {
            $path = base_path($script);

            $this->assertFileExists($path, "Missing sync script: {$script}");
            $this->assertTrue(is_executable($path), "Sync script is not executable: {$script}");
        }
    }

    public function test_import_to_local_dry_run_exits_successfully(): void
    {
        $fixtureDir = base_path('tests/fixtures/sync');

        $this->assertDirectoryExists($fixtureDir);

        $process = new Process(
            [
                base_path('scripts/sync/import-to-env.sh'),
                'local',
                $fixtureDir,
                '--dry-run',
                '--yes',
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_dotenv_loader_preserves_bcrypt_hash_without_shell_expansion(): void
    {
        $envFile = base_path('tests/fixtures/sync/dotenv-bcrypt.env');

        $process = new Process(
            [
                'bash',
                '-c',
                'set -euo pipefail; source scripts/lib/load-dotenv.sh; dotenv_load "$1"; test "$MAILPIT_UI_AUTH" = \'$2y$12$.jpxvb6dOdlKSyeEBWWdnOPH2HDLD2Gr.q3JvLToabMXhayJv7h8K\'',
                'bash',
                $envFile,
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_export_from_prod_dry_run_exits_successfully_when_env_is_present(): void
    {
        if (! is_file(base_path('.env'))) {
            $this->markTestSkipped('Project .env is required for export dry-run.');
        }

        $process = new Process(
            [
                base_path('scripts/sync/export-from-env.sh'),
                'prod',
                '/tmp/nerdik-sync-test-dry-run',
                '--dry-run',
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_export_from_local_dry_run_exits_successfully_when_env_is_present(): void
    {
        if (! is_file(base_path('.env'))) {
            $this->markTestSkipped('Project .env is required for export dry-run.');
        }

        $process = new Process(
            [
                base_path('scripts/sync/export-from-env.sh'),
                'local',
                '/tmp/nerdik-sync-test-local-dry-run',
                '--dry-run',
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_export_with_tables_dry_run_exits_successfully_when_env_is_present(): void
    {
        if (! is_file(base_path('.env'))) {
            $this->markTestSkipped('Project .env is required for export dry-run.');
        }

        $process = new Process(
            [
                base_path('scripts/sync/export-from-env.sh'),
                'prod',
                '/tmp/nerdik-sync-test-dry-run-tables',
                '--dry-run',
                '--tables',
                'users',
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_import_with_tables_dry_run_exits_successfully(): void
    {
        $fixtureDir = base_path('tests/fixtures/sync');

        $this->assertDirectoryExists($fixtureDir);

        $process = new Process(
            [
                base_path('scripts/sync/import-to-env.sh'),
                'local',
                $fixtureDir,
                '--dry-run',
                '--yes',
                '--tables',
                'users',
            ],
            base_path(),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_invalid_table_name_is_rejected(): void
    {
        $process = new Process(
            [
                base_path('scripts/sync/export-from-env.sh'),
                'prod',
                '/tmp/nerdik-sync-test-invalid-table',
                '--dry-run',
                '--tables',
                'users;drop',
            ],
            base_path(),
        );

        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString(
            'invalid table name',
            $process->getErrorOutput().$process->getOutput(),
        );
    }

    public function test_sync_from_prod_help_exits_successfully(): void
    {
        $process = new Process(
            [base_path('scripts/sync/sync-from-prod.sh'), '--help'],
            base_path(),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('APP_ENV', $process->getOutput());
    }

    public function test_sync_from_prod_is_blocked_on_production(): void
    {
        $root = $this->makeTempCheckout('production');

        try {
            $process = new Process(
                [base_path('scripts/sync/sync-from-prod.sh'), '--dry-run'],
                base_path(),
                array_merge($_ENV, ['NERDIK_CHECKOUT_ROOT' => $root]),
            );
            $process->run();

            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString(
                'blocked on production',
                $process->getErrorOutput().$process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_sync_to_staging_is_blocked_when_not_local(): void
    {
        $root = $this->makeTempCheckout('staging');

        try {
            $process = new Process(
                [base_path('scripts/sync/sync-to-staging.sh'), '--dry-run'],
                base_path(),
                array_merge($_ENV, ['NERDIK_CHECKOUT_ROOT' => $root]),
            );
            $process->run();

            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString(
                'local-only',
                $process->getErrorOutput().$process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_sync_to_staging_help_exits_successfully(): void
    {
        $process = new Process(
            [base_path('scripts/sync/sync-to-staging.sh'), '--help'],
            base_path(),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('APP_ENV=local', $process->getOutput());
    }

    /**
     * @return non-empty-string
     */
    private function makeTempCheckout(string $appEnv): string
    {
        $root = sys_get_temp_dir().'/nerdik-sync-test-'.uniqid('', true);
        mkdir($root, 0755, true);
        file_put_contents($root.'/.env', "APP_ENV={$appEnv}\n");

        return $root;
    }

    private function removeTempCheckout(string $root): void
    {
        $envFile = $root.'/.env';
        if (is_file($envFile)) {
            unlink($envFile);
        }

        if (is_dir($root)) {
            rmdir($root);
        }
    }
}
