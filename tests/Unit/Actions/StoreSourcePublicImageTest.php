<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Images\StoreSourcePublicImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class StoreSourcePublicImageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_source_image_as_webp_on_public_disk(): void
    {
        Storage::fake('public');

        $path = app(StoreSourcePublicImage::class)(
            'sources/test.webp',
            UploadedFile::fake()->image('source.jpg', 1920, 1080),
        );

        $this->assertSame('sources/test.webp', $path);
        Storage::disk('public')->assertExists('sources/test.webp');

        $bytes = Storage::disk('public')->get('sources/test.webp');
        $this->assertNotFalse($bytes);
        $this->assertSame('image/webp', finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $bytes));
    }

    #[Test]
    public function it_scales_down_images_larger_than_max_edge(): void
    {
        Storage::fake('public');

        app(StoreSourcePublicImage::class)(
            'sources/large.webp',
            UploadedFile::fake()->image('huge.jpg', 5000, 3000),
        );

        $absolute = Storage::disk('public')->path('sources/large.webp');
        $image = app(ImageManager::class)->read($absolute);

        $this->assertLessThanOrEqual(4096, $image->width());
        $this->assertLessThanOrEqual(4096, $image->height());
        $this->assertSame(4096, max($image->width(), $image->height()));
    }

    #[Test]
    public function it_throws_when_public_disk_write_fails(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);

        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write source image to public disk at [sources/test.webp].');

        app(StoreSourcePublicImage::class)(
            'sources/test.webp',
            UploadedFile::fake()->image('source.jpg', 800, 600),
        );
    }
}
