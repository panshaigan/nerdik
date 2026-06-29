<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\StaticPictureSources;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StaticPictureSourcesTest extends TestCase
{
    #[Test]
    public function it_builds_split_mobile_and_desktop_srcsets(): void
    {
        $sources = StaticPictureSources::fromAppShellBackground('dark');

        $webpMobile = $sources->webpMobileSrcset();
        $webpDesktop = $sources->webpDesktopSrcset();

        $this->assertStringContainsString('384w', $webpMobile);
        $this->assertStringContainsString('images/app/backgrounds/dark/384w.webp', $webpMobile);
        $this->assertStringNotContainsString('1716w', $webpMobile);
        $this->assertStringNotContainsString('1280w', $webpMobile);

        $this->assertStringContainsString('1716w', $webpDesktop);
        $this->assertStringContainsString('1536w', $webpDesktop);
        $this->assertStringContainsString('images/app/backgrounds/dark/1716w.webp', $webpDesktop);
        $this->assertStringNotContainsString('1280w', $webpDesktop);
        $this->assertStringNotContainsString('384w', $webpDesktop);

        $this->assertSame('100vw', $sources->sizes());
        $this->assertSame('(max-width: 1024px)', $sources->mobileMediaQuery());
        $this->assertSame('(min-width: 1025px)', $sources->desktopMediaQuery());
        $this->assertSame('', $sources->alt());
        $this->assertSame('', $sources->jpegSrcset());
        $this->assertSame(1716, $sources->width());
        $this->assertSame(916, $sources->height());
        $this->assertStringContainsString('images/app/backgrounds/dark/1716w.webp', $sources->displaySrc());
    }

    #[Test]
    public function light_theme_reads_light_manifest_entries(): void
    {
        $sources = StaticPictureSources::fromAppShellBackground('light');

        $this->assertStringContainsString('images/app/backgrounds/light/512w.webp', $sources->webpMobileSrcset());
        $this->assertStringContainsString('images/app/backgrounds/light/1716w.webp', $sources->displaySrc());
    }
}
