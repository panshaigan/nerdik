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
}
