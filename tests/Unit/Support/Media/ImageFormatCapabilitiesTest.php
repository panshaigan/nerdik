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
    public function production_conversion_formats_use_config_and_filter_unsupported_avif(): void
    {
        config(['media.conversion_formats' => ['avif', 'webp']]);

        $formatNames = array_column(
            ImageFormatCapabilities::productionConversionFormats(),
            'name',
        );

        if (! ImageFormatCapabilities::supportsAvif()) {
            $this->assertSame(['webp'], $formatNames);
        } else {
            $this->assertSame(['avif', 'webp'], $formatNames);
        }
    }

    #[Test]
    public function production_conversion_formats_default_to_webp_from_config(): void
    {
        config(['media.conversion_formats' => ['webp']]);

        $formatNames = array_column(
            ImageFormatCapabilities::productionConversionFormats(),
            'name',
        );

        $this->assertSame(['webp'], $formatNames);
    }

    #[Test]
    public function filter_supported_format_names_removes_avif_when_unsupported(): void
    {
        $filtered = ImageFormatCapabilities::filterSupportedFormatNames(['avif', 'webp']);

        if (ImageFormatCapabilities::supportsAvif()) {
            $this->assertSame(['avif', 'webp'], $filtered);
        } else {
            $this->assertSame(['webp'], $filtered);
        }
    }
}
