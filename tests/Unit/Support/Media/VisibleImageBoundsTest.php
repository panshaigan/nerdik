<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\VisibleImageBounds;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class VisibleImageBoundsTest extends TestCase
{
    private const FIXTURE = 'tests/fixtures/visible-image-bounds/bright-core-with-dark-stroke.png';

    #[Test]
    public function it_expands_bright_core_bounds_by_padding_to_keep_dark_stroke(): void
    {
        $source = base_path(self::FIXTURE);
        $size = getimagesize($source);

        $this->assertNotFalse($size);

        [$canvasWidth, $canvasHeight] = $size;
        $bounds = VisibleImageBounds::forPath($source, padding: 10);

        // Fixture: bright core at (50,50)–(149,149); padding 10 reaches the dark stroke ring.
        $this->assertSame(40, $bounds['x']);
        $this->assertSame(40, $bounds['y']);
        $this->assertSame(120, $bounds['width']);
        $this->assertSame(120, $bounds['height']);
        $this->assertLessThan($canvasWidth, $bounds['width']);
        $this->assertLessThan($canvasHeight, $bounds['height']);
    }

    #[Test]
    public function it_clamps_padded_bounds_to_the_canvas(): void
    {
        $source = base_path(self::FIXTURE);
        $size = getimagesize($source);

        $this->assertNotFalse($size);

        [$canvasWidth, $canvasHeight] = $size;
        $bounds = VisibleImageBounds::forPath($source, padding: 96);

        $this->assertSame(0, $bounds['x']);
        $this->assertSame(0, $bounds['y']);
        $this->assertSame($canvasWidth, $bounds['width']);
        $this->assertSame($canvasHeight, $bounds['height']);
    }

    #[Test]
    public function it_rejects_invalid_luminance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid min luminance [256].');

        VisibleImageBounds::forPath(base_path(self::FIXTURE), minLuminance: 256);
    }
}
