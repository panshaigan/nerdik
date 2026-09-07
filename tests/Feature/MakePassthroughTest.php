<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class MakePassthroughTest extends TestCase
{
    public function test_passthrough_scripts_exist_and_are_executable(): void
    {
        foreach (['bin/make', 'scripts/make-passthrough.sh'] as $relative) {
            $path = base_path($relative);

            $this->assertFileExists($path);
            $this->assertTrue(is_executable($path), $relative.' should be executable');
        }
    }

    public function test_bin_make_forwards_dashed_equals_args_to_artisan(): void
    {
        $dsn = 'https://example@o123.ingest.sentry.io/456';

        $process = $this->binMakeDryRun([
            'artisan',
            'sentry:publish',
            '--dsn='.$dsn,
        ]);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertSame(
            "artisan sentry:publish --dsn={$dsn}\n",
            $process->getOutput(),
        );
    }

    public function test_bin_make_preserves_npm_double_dash_separator(): void
    {
        $process = $this->binMakeDryRun([
            'npm',
            'run',
            'build',
            '--',
            '--mode=production',
        ]);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertSame(
            "npm run build -- --mode=production\n",
            $process->getOutput(),
        );
    }

    public function test_bin_make_forwards_composer_and_test_flags(): void
    {
        $composer = $this->binMakeDryRun(['composer', 'require', 'foo/bar', '--dev']);
        $this->assertTrue($composer->isSuccessful(), $composer->getErrorOutput().$composer->getOutput());
        $this->assertSame("composer require foo/bar --dev\n", $composer->getOutput());

        $test = $this->binMakeDryRun(['test', '--filter=FooTest', '--parallel']);
        $this->assertTrue($test->isSuccessful(), $test->getErrorOutput().$test->getOutput());
        $this->assertSame("test --filter=FooTest --parallel\n", $test->getOutput());
    }

    public function test_bin_make_exports_assignments_before_passthrough_target(): void
    {
        $process = $this->binMakeDryRun(['YES=1', 'artisan', 'migrate:fresh', '--force']);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertSame("artisan migrate:fresh --force\n", $process->getOutput());
    }

    public function test_makefile_args_variable_forwards_verbatim(): void
    {
        $process = new Process(
            [
                '/usr/bin/make',
                'artisan',
                'ARGS=sentry:publish --dsn=https://example.com/1',
            ],
            base_path(),
            array_merge($_ENV, [
                'NERDIK_PASSTHROUGH_DRY_RUN' => '1',
            ]),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertSame(
            "artisan sentry:publish --dsn=https://example.com/1\n",
            $process->getOutput(),
        );
    }

    public function test_bin_make_delegates_non_passthrough_to_real_make(): void
    {
        $process = new Process(
            [base_path('bin/make'), '-v'],
            base_path(),
            array_merge($_ENV, [
                'NERDIK_REAL_MAKE' => '/usr/bin/make',
            ]),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('GNU Make', $process->getOutput());
    }

    /**
     * @param  list<string>  $args
     */
    private function binMakeDryRun(array $args): Process
    {
        $process = new Process(
            [base_path('bin/make'), ...$args],
            base_path(),
            array_merge($_ENV, [
                'NERDIK_PASSTHROUGH_DRY_RUN' => '1',
                'NERDIK_REAL_MAKE' => '/usr/bin/make',
            ]),
        );
        $process->run();

        return $process;
    }
}
