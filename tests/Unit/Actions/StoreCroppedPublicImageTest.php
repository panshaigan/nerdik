<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Images\StoreCroppedPublicImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StoreCroppedPublicImageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_cover_image_as_webp_on_public_disk(): void
    {
        Storage::fake('public');

        $path = app(StoreCroppedPublicImage::class)(
            'covers/test.webp',
            UploadedFile::fake()->image('source.jpg', 1920, 1080),
            1280,
            720,
        );

        $this->assertSame('covers/test.webp', $path);
        Storage::disk('public')->assertExists('covers/test.webp');
    }
}
