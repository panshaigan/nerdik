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
        $logo = BrandLogoSources::fromManifest()->forPreset('xl');

        $this->assertStringContainsString('ui-brand', $html);
        $this->assertStringContainsString('ui-brand-name', $html);
        $this->assertStringContainsString('images/app/brand/192w.webp', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString((string) config('app.name'), $html);
        $this->assertStringContainsString('--brand-logo-height: 159px', $html);
        $this->assertStringContainsString('--brand-wordmark-ratio: 0.5', $html);
        $this->assertStringContainsString(
            'font-size: calc(var(--brand-logo-height) * var(--brand-wordmark-ratio))',
            $html,
        );
        $this->assertSame(80, $logo['wordmark_font_size']);
        $this->assertEqualsWithDelta(0.5, $logo['wordmark_ratio'], 0.001);
    }

    public function test_brand_lockup_accepts_a_wordmark_ratio_override(): void
    {
        $html = Blade::render('<x-brand size="xl" :wordmark-ratio="0.35" />');

        $this->assertStringContainsString('--brand-wordmark-ratio: 0.35', $html);
        $this->assertStringContainsString('--brand-logo-height: 159px', $html);
    }

    public function test_brand_lockup_nav_wordmark_follows_configured_ratio(): void
    {
        $html = Blade::render('<x-brand size="nav" />');
        $logo = BrandLogoSources::fromManifest()->forPreset('nav');

        $this->assertStringContainsString('images/app/brand/40w.webp', $html);
        $this->assertStringContainsString('--brand-logo-height: 36px', $html);
        $this->assertStringContainsString('--brand-wordmark-ratio: 0.5', $html);
        $this->assertSame(18, $logo['wordmark_font_size']);
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
        $response->assertSee('--brand-logo-height: 80px', false);
        $response->assertSee('images/app/brand/96w.webp', false);
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
