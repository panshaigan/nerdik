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

    public function test_vps_deploy_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/vps-deploy.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
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
