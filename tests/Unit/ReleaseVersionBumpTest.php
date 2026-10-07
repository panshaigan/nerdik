<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ReleaseVersionBumpTest extends TestCase
{
    private string $versionScript;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionScript = base_path('scripts/lib/version.sh');
        $this->assertFileExists($this->versionScript);
        $this->assertTrue(is_executable($this->versionScript));
    }

    #[Test]
    public function normalize_strips_v_prefix(): void
    {
        $this->assertSame('1.2.3', $this->runVersionCli('normalize', 'v1.2.3'));
    }

    #[Test]
    public function bump_minor_increments_last_segment(): void
    {
        $this->assertSame('1.10.14', $this->runVersionCli('bump-minor', '1.10.13'));
    }

    #[Test]
    public function bump_feature_increments_middle_and_resets_patch(): void
    {
        $this->assertSame('1.11.0', $this->runVersionCli('bump-feature', '1.10.13'));
    }

    #[Test]
    public function bump_major_increments_first_and_resets_rest(): void
    {
        $this->assertSame('2.0.0', $this->runVersionCli('bump-major', '1.10.13'));
    }

    #[Test]
    public function resolve_accepts_strict_semver(): void
    {
        $this->assertSame('1.12.5', $this->runVersionCli('resolve', '1.12.5', '1.10.13'));
        $this->assertSame('2.0.0', $this->runVersionCli('resolve', 'v2.0.0', '1.10.13'));
    }

    #[Test]
    public function resolve_defaults_empty_mode_to_minor_bump(): void
    {
        $this->assertSame('1.10.14', $this->runVersionCli('resolve', '', '1.10.13'));
    }

    #[Test]
    public function resolve_rejects_invalid_strict_version(): void
    {
        $process = $this->versionProcess('resolve', 'not-a-version', '1.10.13');

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Invalid version', $process->getErrorOutput());
    }

    #[Test]
    public function validate_accepts_semver(): void
    {
        $this->assertSame('ok', $this->runVersionCli('validate', '1.0.0'));
    }

    #[Test]
    public function validate_rejects_invalid_semver(): void
    {
        $process = $this->versionProcess('validate', '1.0');

        $this->assertFalse($process->isSuccessful());
    }

    private function runVersionCli(string ...$args): string
    {
        $process = $this->versionProcess(...$args);

        $this->assertTrue(
            $process->isSuccessful(),
            trim($process->getErrorOutput().$process->getOutput()),
        );

        return trim($process->getOutput());
    }

    private function versionProcess(string ...$args): Process
    {
        $process = new Process(array_merge([$this->versionScript], $args), base_path());
        $process->run();

        return $process;
    }
}
