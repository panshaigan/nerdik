<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\BrandLogoSources;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrandLogoSourcesTest extends TestCase
{
    #[Test]
    public function it_resolves_nav_preset_with_retina_srcset(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('nav');

        $this->assertStringContainsString('images/app/brand/40w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/40w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/80w.webp 2x', $logo['srcset']);
        $this->assertSame(39, $logo['width']);
        $this->assertSame(36, $logo['height']);
        $this->assertSame(18, $logo['wordmark_font_size']);
        $this->assertEqualsWithDelta(0.5, $logo['wordmark_ratio'], 0.001);
    }

    #[Test]
    public function it_resolves_admin_preset_for_filament_logo_height(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('admin');

        $this->assertStringContainsString('images/app/brand/40w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/64w.webp 2x', $logo['srcset']);
        $this->assertSame(34, $logo['width']);
        $this->assertSame(31, $logo['height']);
    }

    #[Test]
    public function it_resolves_lg_preset_from_larger_variants(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('lg');

        $this->assertStringContainsString('images/app/brand/128w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/128w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame(128, $logo['width']);
        $this->assertSame(118, $logo['height']);
    }

    #[Test]
    public function it_resolves_xl_preset_from_largest_variant(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('xl');

        $this->assertStringContainsString('images/app/brand/192w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/192w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame(172, $logo['width']);
        $this->assertSame(159, $logo['height']);
        $this->assertSame(80, $logo['wordmark_font_size']);
        $this->assertEqualsWithDelta(0.5, $logo['wordmark_ratio'], 0.001);
    }

    #[Test]
    public function it_sizes_wordmark_to_configured_ratio_of_logo_height(): void
    {
        $this->assertSame(14, BrandLogoSources::wordmarkFontSizeForHeight(36));
        $this->assertSame(32, BrandLogoSources::wordmarkFontSizeForHeight(80));
        $this->assertSame(64, BrandLogoSources::wordmarkFontSizeForHeight(160));
    }

    #[Test]
    public function it_uses_preset_wordmark_ratio_and_allows_an_override(): void
    {
        config(['media.brand_logo.presets.xl.wordmark_ratio' => 0.5]);

        $fromPreset = BrandLogoSources::fromManifest()->forPreset('xl');
        $fromOverride = BrandLogoSources::fromManifest()->forPreset('xl', 0.6);

        $this->assertSame(80, $fromPreset['wordmark_font_size']);
        $this->assertEqualsWithDelta(0.5, $fromPreset['wordmark_ratio'], 0.001);
        $this->assertSame(95, $fromOverride['wordmark_font_size']);
        $this->assertEqualsWithDelta(0.6, $fromOverride['wordmark_ratio'], 0.001);
        $this->assertSame(80, BrandLogoSources::wordmarkFontSizeForHeight(160, 0.5));
    }

    #[Test]
    public function it_rejects_invalid_wordmark_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid brand wordmark ratio [0].');

        BrandLogoSources::fromManifest()->forPreset('xl', 0.0);
    }

    #[Test]
    public function it_rejects_unknown_presets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown brand logo preset [2xl].');

        BrandLogoSources::fromManifest()->forPreset('2xl');
    }
}
