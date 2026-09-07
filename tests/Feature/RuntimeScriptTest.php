<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class RuntimeScriptTest extends TestCase
{
    public function test_runtime_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/lib/runtime.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
    }

    public function test_runtime_print_maps_local_to_sail(): void
    {
        $root = $this->makeTempCheckout('local');

        try {
            $process = $this->runtimePrint($root);
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame(
                "RUNTIME=sail\nDEPLOY_ENV=\nAPP_ENV=local\n",
                $process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_runtime_print_maps_staging_to_stack(): void
    {
        $root = $this->makeTempCheckout('staging');

        try {
            $process = $this->runtimePrint($root);
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame(
                "RUNTIME=stack\nDEPLOY_ENV=staging\nAPP_ENV=staging\n",
                $process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_runtime_print_maps_production_to_prod_stack(): void
    {
        $root = $this->makeTempCheckout('production');

        try {
            $process = $this->runtimePrint($root);
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame(
                "RUNTIME=stack\nDEPLOY_ENV=prod\nAPP_ENV=production\n",
                $process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_runtime_print_rejects_unsupported_app_env(): void
    {
        $root = $this->makeTempCheckout('testing');

        try {
            $process = $this->runtimePrint($root);
            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString('Unsupported APP_ENV=testing', $process->getErrorOutput());
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_runtime_print_honors_nerdik_deploy_env_override(): void
    {
        $root = $this->makeTempCheckout('local');

        try {
            $process = new Process(
                [base_path('scripts/lib/runtime.sh'), '--print', $root],
                base_path(),
                array_merge($_ENV, ['NERDIK_DEPLOY_ENV' => 'staging']),
            );
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame(
                "RUNTIME=stack\nDEPLOY_ENV=staging\nAPP_ENV=local\n",
                $process->getOutput(),
            );
        } finally {
            $this->removeTempCheckout($root);
        }
    }

    public function test_app_cmd_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/app-cmd.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
    }

    public function test_boost_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/lib/boost.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
    }

    public function test_boost_verify_rejects_missing_mcp_config(): void
    {
        $missingConfig = sys_get_temp_dir().'/nerdik-missing-mcp-'.uniqid('', true).'.json';

        $process = new Process(
            [base_path('scripts/lib/boost.sh'), 'verify', 'php', $missingConfig],
            base_path(),
        );
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString(
            'Missing '.$missingConfig,
            $process->getErrorOutput(),
        );
    }

    public function test_boost_mcp_health_check_succeeds_with_host_php(): void
    {
        $process = new Process(
            [base_path('scripts/lib/boost.sh'), 'verify', 'php'],
            base_path(),
        );
        $process->setTimeout(30);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            $process->getErrorOutput().$process->getOutput(),
        );
        $this->assertStringContainsString('Boost MCP OK.', $process->getOutput());
    }

    public function test_vps_deploy_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/vps-deploy.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
    }

    public function test_production_destructive_confirm_skipped_when_not_production(): void
    {
        $process = $this->confirmDestructive('local', "should-not-matter\n");

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringNotContainsString('WARNING', $process->getErrorOutput());
    }

    public function test_production_destructive_confirm_skipped_with_yes_env(): void
    {
        $process = $this->confirmDestructive('production', "no\n", ['YES' => '1']);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_production_destructive_confirm_aborts_when_answer_is_wrong(): void
    {
        $process = $this->confirmDestructive('production', "yes\n");

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('WARNING: DESTRUCTIVE COMMAND ON PRODUCTION', $process->getErrorOutput());
        $this->assertStringContainsString('Aborted.', $process->getErrorOutput());
    }

    public function test_production_destructive_confirm_accepts_typed_production(): void
    {
        $process = $this->confirmDestructive('production', "production\n");

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('WARNING: DESTRUCTIVE COMMAND ON PRODUCTION', $process->getErrorOutput());
    }

    public function test_production_destructive_confirm_rejects_non_tty_without_yes(): void
    {
        $script = <<<'BASH'
set -euo pipefail
source scripts/lib/runtime.sh
APP_ENV=production
DEPLOY_ENV=prod
# Force the non-TTY branch even if PHPUnit attaches a PTY.
exec 0</dev/null
runtime_confirm_production_destructive 'make fresh' 'drops tables'
BASH;

        $process = new Process(
            ['bash', '-c', $script],
            base_path(),
        );
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('without a TTY', $process->getErrorOutput());
        $this->assertStringContainsString('YES=1', $process->getErrorOutput());
    }

    public function test_runtime_is_destructive_artisan_detects_dangerous_commands(): void
    {
        $script = <<<'BASH'
set -euo pipefail
source scripts/lib/runtime.sh
runtime_is_destructive_artisan migrate:fresh
runtime_is_destructive_artisan db:seed
runtime_is_destructive_artisan app:init
! runtime_is_destructive_artisan migrate
! runtime_is_destructive_artisan about
BASH;

        $process = new Process(
            ['bash', '-c', $script],
            base_path(),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    /**
     * @param  array<string, string>  $env
     */
    private function confirmDestructive(string $appEnv, string $stdin, array $env = []): Process
    {
        $script = <<<'BASH'
set -euo pipefail
source scripts/lib/runtime.sh
APP_ENV="$TEST_APP_ENV"
DEPLOY_ENV=prod
runtime_confirm_production_destructive 'make fresh' 'DROPS ALL TABLES'
BASH;

        $process = new Process(
            ['bash', '-c', $script],
            base_path(),
            array_merge($_ENV, $env, [
                'TEST_APP_ENV' => $appEnv,
                // Allow piping answers in PHPUnit (stdin is not a TTY).
                'NERDIK_DESTRUCTIVE_REQUIRE_TTY' => '0',
            ]),
        );
        $process->setInput($stdin);
        $process->run();

        return $process;
    }

    /**
     * @return non-empty-string
     */
    private function makeTempCheckout(string $appEnv): string
    {
        $root = sys_get_temp_dir().'/nerdik-runtime-test-'.uniqid('', true);
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

    private function runtimePrint(string $root): Process
    {
        $process = new Process(
            [base_path('scripts/lib/runtime.sh'), '--print', $root],
            base_path(),
        );
        $process->run();

        return $process;
    }
}
