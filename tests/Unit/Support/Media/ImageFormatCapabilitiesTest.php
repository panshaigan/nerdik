<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\ImageFormatCapabilities;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImageFormatCapabilitiesTest extends TestCase
{
    #[Test]
    public function supports_avif_matches_php_capability(): void
    {
        $this->assertSame(function_exists('imageavif'), ImageFormatCapabilities::supportsAvif());
    }

    #[Test]
    public function production_conversion_formats_never_include_avif_when_unsupported(): void
    {
        $formatNames = array_column(
            ImageFormatCapabilities::productionConversionFormats(),
            'name',
        );

        if (! ImageFormatCapabilities::supportsAvif()) {
            $this->assertNotContains('avif', $formatNames);
        } else {
            $this->assertContains('avif', $formatNames);
        }

        $this->assertSame(['webp', 'jpeg'], array_values(array_diff($formatNames, ['avif'])));
    }

    #[Test]
    public function filter_supported_format_names_removes_avif_when_unsupported(): void
    {
        $filtered = ImageFormatCapabilities::filterSupportedFormatNames(['avif', 'webp', 'jpeg']);

        if (ImageFormatCapabilities::supportsAvif()) {
            $this->assertSame(['avif', 'webp', 'jpeg'], $filtered);
        } else {
            $this->assertSame(['webp', 'jpeg'], $filtered);
        }
    }
}
