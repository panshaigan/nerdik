<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Support\Ui\GeneratedAvatarColors;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GeneratedAvatarColorsTest extends TestCase
{
    #[Test]
    public function it_detects_null_colors_as_default(): void
    {
        $this->assertTrue(GeneratedAvatarColors::usesDefaultColors(null, null));
    }

    #[Test]
    public function it_detects_legacy_default_hex_pair_as_default(): void
    {
        $this->assertTrue(GeneratedAvatarColors::usesDefaultColors('#1d4ed8', '#ffffff'));
        $this->assertTrue(GeneratedAvatarColors::usesDefaultColors('#1D4ED8', '#FFFFFF'));
    }

    #[Test]
    public function it_does_not_treat_custom_colors_as_default(): void
    {
        $this->assertFalse(GeneratedAvatarColors::usesDefaultColors('#112233', '#ddeeff'));
        $this->assertFalse(GeneratedAvatarColors::usesDefaultColors('#1d4ed8', null));
    }

    #[Test]
    public function random_returns_palette_background_and_white_text(): void
    {
        $colors = GeneratedAvatarColors::random();

        $this->assertSame(GeneratedAvatarColors::defaultText(), $colors['text']);
        $this->assertContains($colors['background'], GeneratedAvatarColors::palette());
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colors['background']);
    }
}
