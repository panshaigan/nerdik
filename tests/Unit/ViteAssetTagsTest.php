<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

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
    }
}
