<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\VisibleImageBounds;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VisibleImageBoundsTest extends TestCase
{
    #[Test]
    public function it_keeps_dark_stroke_padding_around_the_bright_logo_core(): void
    {
        $source = base_path('resources/brand/nerdik_brand_logo.webp');
        $size = getimagesize($source);

        $this->assertNotFalse($size);

        [$canvasWidth, $canvasHeight] = $size;
        $bounds = VisibleImageBounds::forPath($source);

        $this->assertSame(64, $bounds['x']);
        $this->assertSame(80, $bounds['y']);
        $this->assertSame(1139, $bounds['width']);
        $this->assertSame(1054, $bounds['height']);
        $this->assertLessThan($canvasWidth, $bounds['width']);
        $this->assertLessThan($canvasHeight, $bounds['height']);
        $this->assertGreaterThan(20, $bounds['y']);
        $this->assertLessThan(100, $bounds['y']);
    }

    #[Test]
    public function it_rejects_invalid_luminance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid min luminance [256].');

        VisibleImageBounds::forPath(base_path('resources/brand/nerdik_brand_logo.webp'), minLuminance: 256);
    }
}
