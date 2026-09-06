<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkflowReleaseTriggersTest extends TestCase
{
    #[Test]
    public function ci_runs_on_pull_requests_and_version_tags_not_main_pushes(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertIsString($ci);
        $this->assertStringContainsString('pull_request:', $ci);
        $this->assertStringContainsString("tags:\n      - v*", $ci);
        $this->assertStringNotContainsString("branches:\n      - main", $ci);
    }

    #[Test]
    public function docker_publishes_only_on_version_tags(): void
    {
        $docker = file_get_contents(base_path('.github/workflows/docker.yml'));

        $this->assertIsString($docker);
        $this->assertStringContainsString("tags:\n      - v*", $docker);
        $this->assertStringNotContainsString('pull_request:', $docker);
        $this->assertStringNotContainsString("branches:\n      - main", $docker);
        $this->assertStringContainsString('push: true', $docker);
    }

    #[Test]
    public function release_creates_github_release_only_on_version_tags(): void
    {
        $release = file_get_contents(base_path('.github/workflows/release.yml'));

        $this->assertIsString($release);
        $this->assertStringContainsString("tags:\n      - v*", $release);
        $this->assertStringNotContainsString('pull_request:', $release);
        $this->assertStringNotContainsString("branches:\n      - main", $release);
        $this->assertStringContainsString('contents: write', $release);
        $this->assertStringContainsString('softprops/action-gh-release', $release);
        $this->assertStringContainsString('generate_release_notes: true', $release);
    }
}
