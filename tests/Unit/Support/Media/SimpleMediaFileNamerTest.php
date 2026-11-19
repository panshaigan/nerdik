<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\SimpleMediaFileNamer;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Conversion;
use Tests\TestCase;

final class SimpleMediaFileNamerTest extends TestCase
{
    #[Test]
    public function it_uses_flat_original_conversion_and_responsive_stems(): void
    {
        $namer = new SimpleMediaFileNamer;

        $this->assertSame('original', $namer->originalFileName('upload-stem.jpg'));
        $this->assertSame('responsive', $namer->responsiveFileName('anything.webp'));

        $conversion = (new Conversion('avatar_32'))->performOnCollections('avatar');
        $this->assertSame('avatar_32', $namer->conversionFileName('user-photo.png', $conversion));
    }
}
