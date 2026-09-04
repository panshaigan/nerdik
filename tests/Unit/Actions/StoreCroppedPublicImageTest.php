<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Images\StoreCroppedPublicImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class StoreCroppedPublicImageTest extends TestCase
{
    #[Test]
    public function it_stores_cover_image_as_webp_on_public_disk(): void
    {
        Storage::fake('public');

        $path = app(StoreCroppedPublicImage::class)(
            'covers/test.webp',
            UploadedFile::fake()->image('source.jpg', 800, 450),
            1280,
            720,
        );

        $this->assertSame('covers/test.webp', $path);
        Storage::disk('public')->assertExists('covers/test.webp');
    }

    #[Test]
    public function it_throws_when_public_disk_write_fails(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);

        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write image to public disk at [covers/test.webp].');

        app(StoreCroppedPublicImage::class)(
            'covers/test.webp',
            UploadedFile::fake()->image('source.jpg', 800, 450),
            1280,
            720,
        );
    }
}
