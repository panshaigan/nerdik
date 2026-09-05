<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class BrandLogoComponentTest extends TestCase
{
    public function test_welcome_page_renders_resized_brand_logo_webp_image(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('images/app/brand/48w.webp', false);
        $response->assertSee('images/app/brand/96w.webp', false);
        $response->assertSee('width="43"', false);
        $response->assertSee('height="40"', false);
        $response->assertSee('alt="'.config('app.name').'"', false);
        $response->assertDontSee('images/app/nerdik_brand_logo.webp', false);
    }

    public function test_brand_logo_component_renders_preset_variant_with_srcset(): void
    {
        $html = Blade::render(
            '<x-brand-logo size="nav" class="h-9 w-auto" />',
        );

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('images/app/brand/40w.webp', $html);
        $this->assertStringContainsString('images/app/brand/80w.webp 2x', $html);
        $this->assertStringContainsString('width="39"', $html);
        $this->assertStringContainsString('height="36"', $html);
        $this->assertStringContainsString('alt="'.config('app.name').'"', $html);
        $this->assertStringContainsString('class="h-9 w-auto"', $html);
    }
}
