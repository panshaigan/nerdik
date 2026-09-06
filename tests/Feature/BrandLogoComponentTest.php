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
        $response->assertSee('images/app/brand/192w.webp', false);
        $response->assertSee('flex items-center justify-center', false);
        $response->assertSee('alt="'.config('app.name').'"', false);
        $response->assertDontSee('images/app/nerdik_brand_logo.webp', false);
    }

    public function test_brand_logo_component_renders_responsive_webp_sources(): void
    {
        $html = Blade::render(
            '<x-brand-logo size="nav" class="h-9 w-auto" />',
        );

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('images/app/brand/40w.webp', $html);
        $this->assertStringContainsString('alt="'.config('app.name').'"', $html);
    }

    public function test_brand_logo_component_renders_xl_preset(): void
    {
        $html = Blade::render('<x-brand-logo size="xl" />');

        $this->assertStringContainsString('images/app/brand/192w.webp', $html);
        $this->assertStringContainsString('width="172"', $html);
    }
}
