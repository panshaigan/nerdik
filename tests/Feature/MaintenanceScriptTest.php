<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class MaintenanceScriptTest extends TestCase
{
    public function test_maintenance_script_exists_and_is_executable(): void
    {
        $path = base_path('scripts/maintenance.sh');

        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
    }

    public function test_maintenance_script_on_off_status_round_trip(): void
    {
        $stateDir = sys_get_temp_dir().'/nerdik-maintenance-test-'.uniqid('', true);
        mkdir($stateDir, 0755, true);

        try {
            $env = array_merge($_ENV, ['NERDIK_MAINTENANCE_STATE_DIR' => $stateDir]);

            $statusOff = new Process(
                [base_path('scripts/maintenance.sh'), 'status'],
                base_path(),
                $env,
            );
            $statusOff->run();
            $this->assertFalse($statusOff->isSuccessful());
            $this->assertSame("OFF\n", $statusOff->getOutput());

            $on = new Process(
                [base_path('scripts/maintenance.sh'), 'on'],
                base_path(),
                $env,
            );
            $on->run();
            $this->assertTrue($on->isSuccessful(), $on->getErrorOutput().$on->getOutput());
            $this->assertFileExists($stateDir.'/maintenance');

            $statusOn = new Process(
                [base_path('scripts/maintenance.sh'), 'status'],
                base_path(),
                $env,
            );
            $statusOn->run();
            $this->assertTrue($statusOn->isSuccessful());
            $this->assertSame("ON\n", $statusOn->getOutput());

            $off = new Process(
                [base_path('scripts/maintenance.sh'), 'off'],
                base_path(),
                $env,
            );
            $off->run();
            $this->assertTrue($off->isSuccessful(), $off->getErrorOutput().$off->getOutput());
            $this->assertFileDoesNotExist($stateDir.'/maintenance');
        } finally {
            if (is_dir($stateDir)) {
                $files = glob($stateDir.'/*') ?: [];
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($stateDir);
            }
        }
    }

    public function test_caddy_entrypoint_includes_production_maintenance_matcher(): void
    {
        $caddyfilePath = sys_get_temp_dir().'/nerdik-caddyfile-test-'.uniqid('', true);

        $process = new Process(
            [
                'sh',
                '-c',
                'sed "s|/etc/caddy/Caddyfile|'.$caddyfilePath.'|" docker/caddy/entrypoint.sh | sed "s/^exec caddy.*/:/" | sh',
            ],
            base_path(),
            [
                'APP_DOMAIN' => 'example.test',
                'STAGING_DOMAIN' => 'staging.example.test',
                'ACME_EMAIL' => 'ops@example.test',
            ],
        );

        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertFileExists($caddyfilePath);

        $caddyfile = file_get_contents($caddyfilePath);

        $this->assertIsString($caddyfile);
        $this->assertStringContainsString('@maintenance file /etc/caddy/state/maintenance', $caddyfile);
        $this->assertStringContainsString('root * /etc/caddy/maintenance', $caddyfile);
        $this->assertStringContainsString('handle_errors {', $caddyfile);

        unlink($caddyfilePath);
    }

    public function test_maintenance_page_exists(): void
    {
        $path = base_path('docker/caddy/maintenance/index.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('We\'ll be right back', $html);
        $this->assertStringContainsString('updating Nerdik', $html);
    }
}
