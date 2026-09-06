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
    }

    #[Test]
    public function it_resolves_admin_preset_for_filament_logo_height(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('admin');

        $this->assertStringContainsString('images/app/brand/40w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/64w.webp 2x', $logo['srcset']);
        $this->assertSame(34, $logo['width']);
        $this->assertSame(32, $logo['height']);
    }

    #[Test]
    public function it_resolves_lg_preset_from_larger_variants(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('lg');

        $this->assertStringContainsString('images/app/brand/128w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/128w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame(128, $logo['width']);
        $this->assertSame(119, $logo['height']);
    }

    #[Test]
    public function it_resolves_xl_preset_from_largest_variant(): void
    {
        $logo = BrandLogoSources::fromManifest()->forPreset('xl');

        $this->assertStringContainsString('images/app/brand/192w.webp', $logo['src']);
        $this->assertStringContainsString('images/app/brand/192w.webp 1x', $logo['srcset']);
        $this->assertStringContainsString('images/app/brand/192w.webp 2x', $logo['srcset']);
        $this->assertSame(172, $logo['width']);
        $this->assertSame(160, $logo['height']);
    }

    #[Test]
    public function it_rejects_unknown_presets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown brand logo preset [2xl].');

        BrandLogoSources::fromManifest()->forPreset('2xl');
    }
}
