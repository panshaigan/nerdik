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
    public function it_resolves_absolute_path_for_width(): void
    {
        $path = BrandLogoSources::fromManifest()->absolutePathForWidth(64);

        $this->assertSame(public_path('images/app/brand/64w.webp'), $path);
        $this->assertFileExists($path);
    }

    #[Test]
    public function it_resolves_nav_preset_with_retina_srcset(): void
    {
        $sources = BrandLogoSources::fromManifest();
        $logo = $sources->forPreset('nav');
        $displayWidth = (int) config('media.brand_logo.presets.nav.display_width');
        $expectedHeight = $this->expectedDisplayHeight($sources, $displayWidth);
        $expectedRatio = (float) config('media.brand_logo.presets.nav.wordmark_ratio');

        $this->assertStringContainsString('images/app/brand/40w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/40w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/80w.webp 2x', $logo['srcset']);
        $this->assertSame($displayWidth, $logo['width']);
        $this->assertSame($expectedHeight, $logo['height']);
        $this->assertSame(
            BrandLogoSources::wordmarkFontSizeForHeight($expectedHeight, $expectedRatio),
            $logo['wordmark_font_size'],
        );
        $this->assertEqualsWithDelta($expectedRatio, $logo['wordmark_ratio'], 0.001);
    }

    #[Test]
    public function it_resolves_admin_preset_for_filament_logo_height(): void
    {
        $sources = BrandLogoSources::fromManifest();
        $logo = $sources->forPreset('admin');
        $displayWidth = (int) config('media.brand_logo.presets.admin.display_width');

        $this->assertStringContainsString('images/app/brand/40w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/64w.webp 2x', $logo['srcset']);
        $this->assertSame($displayWidth, $logo['width']);
        $this->assertSame($this->expectedDisplayHeight($sources, $displayWidth), $logo['height']);
    }

    #[Test]
    public function it_resolves_lg_preset_from_larger_variants(): void
    {
        $sources = BrandLogoSources::fromManifest();
        $logo = $sources->forPreset('lg');
        $displayWidth = (int) config('media.brand_logo.presets.lg.display_width');

        $this->assertStringContainsString('images/app/brand/128w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/128w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame($displayWidth, $logo['width']);
        $this->assertSame($this->expectedDisplayHeight($sources, $displayWidth), $logo['height']);
    }

    #[Test]
    public function it_resolves_xl_preset_from_largest_variant(): void
    {
        $sources = BrandLogoSources::fromManifest();
        $logo = $sources->forPreset('xl');
        $displayWidth = (int) config('media.brand_logo.presets.xl.display_width');
        $expectedHeight = $this->expectedDisplayHeight($sources, $displayWidth);
        $expectedRatio = (float) config('media.brand_logo.presets.xl.wordmark_ratio');

        $this->assertStringContainsString('images/app/brand/192w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/192w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame($displayWidth, $logo['width']);
        $this->assertSame($expectedHeight, $logo['height']);
        $this->assertSame(
            BrandLogoSources::wordmarkFontSizeForHeight($expectedHeight, $expectedRatio),
            $logo['wordmark_font_size'],
        );
        $this->assertEqualsWithDelta($expectedRatio, $logo['wordmark_ratio'], 0.001);
    }

    #[Test]
    public function it_sizes_wordmark_to_configured_ratio_of_logo_height(): void
    {
        config(['media.brand_logo.wordmark_ratio' => 0.4]);

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

        $this->assertSame(
            BrandLogoSources::wordmarkFontSizeForHeight($fromPreset['height'], 0.5),
            $fromPreset['wordmark_font_size'],
        );
        $this->assertEqualsWithDelta(0.5, $fromPreset['wordmark_ratio'], 0.001);
        $this->assertSame(
            BrandLogoSources::wordmarkFontSizeForHeight($fromOverride['height'], 0.6),
            $fromOverride['wordmark_font_size'],
        );
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

    private function expectedDisplayHeight(BrandLogoSources $sources, int $displayWidth): int
    {
        return (int) round($displayWidth * $sources->intrinsicHeight() / $sources->intrinsicWidth());
    }
}
