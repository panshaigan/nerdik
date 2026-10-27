<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('infra')]
class PlaywrightChromiumScriptTest extends TestCase
{
    public function test_playwright_helper_removes_browsers_installed_inside_node_modules(): void
    {
        $root = sys_get_temp_dir().'/nerdik-playwright-'.uniqid('', true);
        $browsers = $root.'/node_modules/playwright-core/.local-browsers';
        mkdir($browsers.'/.links', 0777, true);
        file_put_contents($browsers.'/.links/keep', 'x');

        try {
            $process = new Process(
                [
                    'bash',
                    '-c',
                    'source "$1" && playwright_remove_node_modules_browsers "$2"',
                    'bash',
                    base_path('scripts/lib/playwright-chromium.sh'),
                    $root,
                ],
                base_path(),
            );
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertDirectoryDoesNotExist($browsers);
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_playwright_helper_ignores_a_missing_node_modules_browser_dir(): void
    {
        $root = sys_get_temp_dir().'/nerdik-playwright-'.uniqid('', true);
        mkdir($root, 0755, true);

        try {
            $process = new Process(
                [
                    'bash',
                    '-c',
                    'source "$1" && playwright_remove_node_modules_browsers "$2"',
                    'bash',
                    base_path('scripts/lib/playwright-chromium.sh'),
                    $root,
                ],
                base_path(),
            );
            $process->run();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        } finally {
            $this->removeTree($root);
        }
    }

    public function test_playwright_path_zero_uses_the_app_user_cache_outside_node_modules(): void
    {
        $script = <<<'BASH'
set -euo pipefail
export PLAYWRIGHT_BROWSERS_PATH=0
source scripts/lib/playwright-chromium.sh
playwright_sanitize_browsers_path
if [[ -n "${PLAYWRIGHT_BROWSERS_PATH:-}" ]]; then
    echo "PLAYWRIGHT_BROWSERS_PATH still set" >&2
    exit 1
fi
dir="$(playwright_browser_cache_dir sail)"
case "$dir" in
    */.cache/ms-playwright) ;;
    *) echo "unexpected cache: $dir" >&2; exit 1 ;;
esac
case "$dir" in
    *node_modules*) echo "cache is inside node_modules: $dir" >&2; exit 1 ;;
esac
BASH;

        $process = new Process(['bash', '-c', $script], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_custom_playwright_browsers_path_is_preserved(): void
    {
        $script = <<<'BASH'
set -euo pipefail
export PLAYWRIGHT_BROWSERS_PATH=/tmp/nerdik-playwright-browsers
source scripts/lib/playwright-chromium.sh
dir="$(playwright_browser_cache_dir sail)"
[[ "$dir" == "/tmp/nerdik-playwright-browsers" ]]
BASH;

        $process = new Process(['bash', '-c', $script], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_release_check_clears_node_modules_browsers_before_npm_ci(): void
    {
        $check = file_get_contents(base_path('scripts/ci-check.sh'));
        $makefile = file_get_contents(base_path('Makefile'));
        $ensure = file_get_contents(base_path('scripts/ensure-playwright-chromium.sh'));

        $this->assertIsString($check);
        $this->assertIsString($makefile);
        $this->assertIsString($ensure);

        $remove = strpos($check, 'playwright_remove_node_modules_browsers');
        $npmCi = strpos($check, '"$SAIL" npm ci');

        $this->assertNotFalse($remove);
        $this->assertNotFalse($npmCi);
        $this->assertLessThan($npmCi, $remove);
        $this->assertStringContainsString('exec -u root', $check);
        $this->assertStringNotContainsString('PLAYWRIGHT_BROWSERS_PATH=0', $makefile);
        $this->assertStringContainsString('runuser -u "$APP_USER"', $ensure);
        $this->assertStringNotContainsString('PLAYWRIGHT_BROWSERS_PATH=0', $ensure);
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
