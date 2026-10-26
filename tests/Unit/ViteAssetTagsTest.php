<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Vite;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('infra')]
class ViteAssetTagsTest extends TestCase
{
    public function test_built_assets_emit_stylesheets_without_css_preloads(): void
    {
        if (Vite::isRunningHot()) {
            $this->markTestSkipped('Vite dev server is running.');
        }

        $html = (string) app(\Illuminate\Foundation\Vite::class)(['resources/js/app.js']);

        $this->assertStringContainsString('rel="stylesheet"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/rel="preload"[^>]+as="style"/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/rel="modulepreload"[^>]+tags-selector/',
            $html,
        );
        $this->assertSame(1, preg_match_all('/rel="modulepreload"/', $html, $modulePreloads));
    }
}
