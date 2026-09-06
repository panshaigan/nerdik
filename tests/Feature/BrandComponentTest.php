<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Media\BrandLogoSources;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class BrandComponentTest extends TestCase
{
    public function test_brand_lockup_renders_logo_and_name_with_scaled_type(): void
    {
        $html = Blade::render('<x-brand size="xl" />');
        $fontSize = BrandLogoSources::fromManifest()->forPreset('xl')['wordmark_font_size'];

        $this->assertStringContainsString('ui-brand', $html);
        $this->assertStringContainsString('ui-brand-name', $html);
        $this->assertStringContainsString('images/app/brand/192w.webp', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString((string) config('app.name'), $html);
        $this->assertStringContainsString("font-size: {$fontSize}px", $html);
        $this->assertGreaterThanOrEqual(80, $fontSize);
    }

    public function test_brand_lockup_nav_wordmark_is_at_least_half_the_logo_height(): void
    {
        $html = Blade::render('<x-brand size="nav" />');
        $logo = BrandLogoSources::fromManifest()->forPreset('nav');

        $this->assertStringContainsString('images/app/brand/40w.webp', $html);
        $this->assertStringContainsString("font-size: {$logo['wordmark_font_size']}px", $html);
        $this->assertGreaterThanOrEqual((int) ceil($logo['height'] / 2), $logo['wordmark_font_size']);
    }

    public function test_brand_lockup_can_render_as_a_link(): void
    {
        $html = Blade::render('<x-brand size="md" href="/" wire:navigate />');

        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('href="/"', $html);
        $this->assertStringContainsString('wire:navigate', $html);
        $this->assertStringContainsString('images/app/brand/96w.webp', $html);
        $this->assertStringContainsString((string) config('app.name'), $html);
    }

    public function test_welcome_page_uses_centered_brand_lockup(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ui-brand', false);
        $response->assertSee('ui-brand-name', false);
        $response->assertSee('images/app/brand/192w.webp', false);
        $response->assertSee((string) config('app.name'), false);
    }

    public function test_guest_layout_uses_brand_lockup(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('ui-brand', false);
        $response->assertSee('ui-brand-name', false);
        $response->assertSee('images/app/brand/96w.webp', false);
        $response->assertSee((string) config('app.name'), false);
    }
}
