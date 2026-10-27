<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('infra')]
final class ReleasePublishScriptTest extends TestCase
{
    #[Test]
    public function release_push_dry_run_skips_make_check_and_bumps_version(): void
    {
        $root = $this->makeCheckout('1.2.3');

        try {
            $process = $this->release($root, [
                'DRY_RUN' => '1',
                'SKIP_CHECK' => '1',
                'SKIP_SMOKE' => '1',
            ]);

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertStringContainsString('would skip make check, then write VERSION=1.2.4', $process->getOutput());
            $this->assertStringContainsString('would commit, push, tag v1.2.4', $process->getOutput());
            $this->assertStringNotContainsString('would run make check', $process->getOutput());
            $this->assertSame("1.2.3\n", (string) file_get_contents($root.'/VERSION'));
        } finally {
            $this->removeTree($root);
        }
    }

    #[Test]
    public function release_push_publishes_a_new_tag_without_running_make_check(): void
    {
        [$root, $origin] = $this->makeGitCheckout('1.2.3');

        try {
            $process = $this->release($root, [
                'SKIP_CHECK' => '1',
                'SKIP_WATCH' => '1',
                'SKIP_SMOKE' => '1',
            ]);

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertStringContainsString('Skipping local CI checks', $process->getOutput());
            $this->assertSame("1.2.4\n", (string) file_get_contents($root.'/VERSION'));
            $head = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD'));
            $tagged = trim((string) shell_exec('git -C '.escapeshellarg($origin).' rev-parse v1.2.4'));
            $this->assertSame($head, $tagged);
        } finally {
            $this->removeTree($root);
            $this->removeTree($origin);
        }
    }

    #[Test]
    public function release_repush_moves_an_existing_tag_onto_head(): void
    {
        [$root, $origin] = $this->makeGitCheckout('1.2.3', tag: true);

        try {
            $before = trim((string) shell_exec('git -C '.escapeshellarg($origin).' rev-parse v1.2.3'));
            file_put_contents($root.'/FIX', "fix\n");
            $commit = new Process(['git', '-C', $root, 'add', 'FIX'], base_path());
            $commit->run();
            $commit = new Process(['git', '-C', $root, 'commit', '-m', 'fix'], base_path());
            $commit->run();
            $this->assertTrue($commit->isSuccessful(), $commit->getErrorOutput());

            $process = $this->release($root, [
                'REPUSH' => '1',
                'SKIP_WATCH' => '1',
                'SKIP_SMOKE' => '1',
            ]);

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertStringContainsString('Repush v1.2.3', $process->getOutput());
            $this->assertSame("1.2.3\n", (string) file_get_contents($root.'/VERSION'));
            $head = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD'));
            $tagged = trim((string) shell_exec('git -C '.escapeshellarg($origin).' rev-parse v1.2.3'));
            $this->assertSame($head, $tagged);
            $this->assertNotSame($before, $tagged);
        } finally {
            $this->removeTree($root);
            $this->removeTree($origin);
        }
    }

    #[Test]
    public function release_repush_refuses_a_tag_that_was_never_pushed(): void
    {
        [$root, $origin] = $this->makeGitCheckout('9.9.9');

        try {
            $process = $this->release($root, [
                'REPUSH' => '1',
                'SKIP_WATCH' => '1',
                'SKIP_SMOKE' => '1',
            ]);

            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString('Tag v9.9.9 does not exist', $process->getErrorOutput());
            $this->assertStringContainsString('make release-push', $process->getErrorOutput());
        } finally {
            $this->removeTree($root);
            $this->removeTree($origin);
        }
    }

    #[Test]
    public function ci_status_ignores_an_older_failed_run_when_a_newer_run_is_in_progress(): void
    {
        $labels = <<<'TXT'
in progress:  Run 121 of CI. chore: release v1.2.3
failed:  Run 120 of CI. chore: release v1.2.3
completed successfully:  Run 119 of Docker. chore: release v1.2.3
TXT;

        $this->assertSame('pending', $this->classify($labels, 120, 119));
    }

    #[Test]
    public function ci_status_succeeds_when_the_newest_ci_and_docker_runs_succeed(): void
    {
        $labels = <<<'TXT'
completed successfully:  Run 122 of Docker. chore: release v1.2.3
completed successfully:  Run 121 of CI. chore: release v1.2.3
failed:  Run 120 of CI. chore: release v1.2.3
TXT;

        $this->assertSame('success', $this->classify($labels, 120, 119));
    }

    /**
     * @param  array<string, string>  $env
     */
    private function release(string $root, array $env): Process
    {
        $exports = '';
        foreach ($env as $key => $value) {
            $exports .= 'export '.$key.'='.escapeshellarg($value)."\n";
        }

        $script = $exports.<<<'BASH'
export RELEASE_ROOT="$ROOT"
export SKIP_SMOKE="${SKIP_SMOKE:-1}"
./scripts/release.sh
BASH;

        $process = new Process(
            ['bash', '-c', $script],
            base_path(),
            array_merge($_ENV, ['ROOT' => $root]),
        );
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    private function classify(string $labels, int $minCi, int $minDocker): string
    {
        $script = <<<'BASH'
set -euo pipefail
source scripts/release.sh
classify_tag_ci_status "$LABELS" "$MIN_CI" "$MIN_DOCKER"
BASH;

        $process = new Process(
            ['bash', '-c', $script],
            base_path(),
            array_merge($_ENV, [
                'LABELS' => $labels,
                'MIN_CI' => (string) $minCi,
                'MIN_DOCKER' => (string) $minDocker,
            ]),
        );
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return trim($process->getOutput());
    }

    private function makeCheckout(string $version): string
    {
        $root = sys_get_temp_dir().'/nerdik-release-'.uniqid('', true);
        mkdir($root, 0755, true);
        file_put_contents($root.'/VERSION', $version."\n");

        return $root;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makeGitCheckout(string $version, bool $tag = false): array
    {
        $root = $this->makeCheckout($version);
        $origin = $root.'-origin.git';
        $tagCommand = $tag
            ? "git -C {$this->shell($root)} tag v{$version} && git -C {$this->shell($root)} push origin v{$version}"
            : 'true';

        $script = <<<BASH
set -euo pipefail
git init -b main {$this->shell($root)}
git -C {$this->shell($root)} config user.email test@example.com
git -C {$this->shell($root)} config user.name Test
git -C {$this->shell($root)} add VERSION
git -C {$this->shell($root)} commit -m init
git init --bare {$this->shell($origin)}
git -C {$this->shell($root)} remote add origin {$this->shell($origin)}
git -C {$this->shell($root)} push -u origin main
{$tagCommand}
BASH;

        $process = new Process(['bash', '-c', $script], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        return [$root, $origin];
    }

    private function shell(string $path): string
    {
        return escapeshellarg($path);
    }

    private function removeTree(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $process = new Process(['rm', '-rf', $root]);
        $process->run();
    }
}
