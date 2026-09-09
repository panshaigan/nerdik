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
    public function release_runs_after_successful_docker_and_gates_on_ci(): void
    {
        $release = file_get_contents(base_path('.github/workflows/release.yml'));

        $this->assertIsString($release);
        $this->assertStringContainsString('workflow_run:', $release);
        $this->assertStringContainsString('- Docker', $release);
        $this->assertStringContainsString('types:', $release);
        $this->assertStringContainsString('- completed', $release);
        $this->assertStringContainsString("github.event.workflow_run.conclusion == 'success'", $release);
        $this->assertStringContainsString('gh run list', $release);
        $this->assertStringContainsString('--workflow ci.yml', $release);
        $this->assertStringContainsString('contents: write', $release);
        $this->assertStringContainsString('softprops/action-gh-release', $release);
        $this->assertStringContainsString('generate_release_notes: true', $release);
        $this->assertStringNotContainsString('pull_request:', $release);
        $this->assertStringNotContainsString("branches:\n      - main", $release);
    }

    #[Test]
    public function deploy_runs_after_successful_release_and_manual_dispatch(): void
    {
        $deploy = file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertIsString($deploy);
        $this->assertStringContainsString('workflow_run:', $deploy);
        $this->assertStringContainsString('- Release', $deploy);
        $this->assertStringContainsString('- completed', $deploy);
        $this->assertStringContainsString("github.event.workflow_run.conclusion == 'success'", $deploy);
        $this->assertStringContainsString('workflow_dispatch:', $deploy);
        $this->assertStringContainsString('environment: production', $deploy);
        $this->assertStringContainsString('appleboy/ssh-action', $deploy);
        $this->assertStringContainsString('./scripts/vps-deploy.sh', $deploy);
        $this->assertStringNotContainsString('--no-pull', $deploy);
        $this->assertStringContainsString('group: deploy-production', $deploy);
        $this->assertStringNotContainsString("release:\n    types:", $deploy);
        $this->assertStringNotContainsString('- published', $deploy);
    }
}
