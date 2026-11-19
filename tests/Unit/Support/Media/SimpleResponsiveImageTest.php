<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Models\Media;
use App\Support\Media\SimpleResponsiveImage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SimpleResponsiveImageTest extends TestCase
{
    #[Test]
    public function it_parses_width_from_simple_responsive_file_names(): void
    {
        $media = new Media;
        $image = new SimpleResponsiveImage('responsive_768.webp', $media);

        $this->assertSame(768, $image->width());
        $this->assertSame('media_library_original', $image->generatedFor());
    }

    #[Test]
    public function it_falls_back_to_legacy_spatie_file_names(): void
    {
        $media = new Media;
        $legacy = 'stem___media_library_original_384_216.webp';
        $image = new SimpleResponsiveImage($legacy, $media);

        $this->assertSame(384, $image->width());
    }
}
