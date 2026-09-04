<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class ConvertPngMediaOriginalsToWebpCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskRoot = storage_path('framework/testing/media-png-webp-'.uniqid());
        File::ensureDirectoryExists($this->diskRoot);

        config([
            'filesystems.disks.public.root' => $this->diskRoot,
            'media.storage_path_prefix' => 'media',
            'media.queue_conversions' => false,
            'media-library.queue_conversions_by_default' => false,
        ]);

        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    #[Test]
    public function dry_run_leaves_png_and_row_unchanged(): void
    {
        $media = $this->createPngMedia('dry-run.png');

        $this->artisan('media:convert-png-originals-to-webp', ['--dry-run' => true])
            ->expectsOutputToContain('Would convert')
            ->expectsOutputToContain('(dry-run)')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame('dry-run.png', $media->file_name);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertFileExists($this->mediaPath($media, 'dry-run.png'));
        $this->assertFileDoesNotExist($this->mediaPath($media, 'dry-run.webp'));
    }

    #[Test]
    public function it_prefers_existing_conversion_webp_over_reencoding_png(): void
    {
        $media = $this->createPngMedia('sample.png', width: 64, height: 36);
        $conversionPath = $this->mediaPath($media, 'conversions/sample-webp.webp');
        File::ensureDirectoryExists(dirname($conversionPath));
        $this->writeWebpFixture($conversionPath, 32, 18);
        $conversionBytes = File::get($conversionPath);

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('Converted')
            ->expectsOutputToContain('compose-exec')
            ->assertSuccessful();

        $media->refresh();
        $target = $this->mediaPath($media, 'sample.webp');

        $this->assertSame('sample.webp', $media->file_name);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(File::size($conversionPath), $media->size);
        $this->assertSame(32, $media->getCustomProperty('width'));
        $this->assertSame(18, $media->getCustomProperty('height'));
        $this->assertFileExists($target);
        $this->assertFileDoesNotExist($this->mediaPath($media, 'sample.png'));
        $this->assertFileExists($conversionPath);
        $this->assertSame($conversionBytes, File::get($target));
        $this->assertSame($conversionBytes, File::get($conversionPath));
    }

    #[Test]
    public function it_reencodes_png_when_conversion_webp_is_missing(): void
    {
        $media = $this->createPngMedia('encode-me.png', width: 48, height: 27);
        $media->generated_conversions = [];
        $media->save();

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('via encode')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame('encode-me.webp', $media->file_name);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertFileExists($this->mediaPath($media, 'encode-me.webp'));
        $this->assertFileDoesNotExist($this->mediaPath($media, 'encode-me.png'));
    }

    #[Test]
    public function it_replaces_oversized_webp_original_with_smaller_conversion(): void
    {
        $media = $this->createWebpMedia('large.webp', width: 200, height: 112);
        $originalPath = $this->mediaPath($media, 'large.webp');
        $this->writeWebpFixture($originalPath, 200, 112);
        $bytesBefore = File::size($originalPath);
        $media->size = $bytesBefore;
        $media->generated_conversions = ['webp' => true];
        $media->save();

        $conversionPath = $this->mediaPath($media, 'conversions/large-webp.webp');
        File::ensureDirectoryExists(dirname($conversionPath));
        $this->writeWebpFixture($conversionPath, 64, 36);
        $this->assertLessThan($bytesBefore, File::size($conversionPath));

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('via conversion')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame('large.webp', $media->file_name);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(File::size($conversionPath), $media->size);
        $this->assertSame(64, $media->getCustomProperty('width'));
        $this->assertSame(36, $media->getCustomProperty('height'));
        $this->assertFileExists($conversionPath);
    }

    #[Test]
    public function it_skips_when_conversion_is_not_smaller(): void
    {
        $media = $this->createWebpMedia('small.webp', width: 32, height: 18);
        $originalPath = $this->mediaPath($media, 'small.webp');
        $this->writeWebpFixture($originalPath, 32, 18);
        $media->size = File::size($originalPath);
        $media->generated_conversions = ['webp' => true];
        $media->save();

        $conversionPath = $this->mediaPath($media, 'conversions/small-webp.webp');
        File::ensureDirectoryExists(dirname($conversionPath));
        File::put($conversionPath, str_repeat('x', $media->size + 100));

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('converted=0')
            ->assertSuccessful();

        $media->refresh();
        $this->assertSame('small.webp', $media->file_name);
        $this->assertSame(32, $media->getCustomProperty('width'));
    }

    #[Test]
    public function it_skips_non_png_media_without_webp_conversion(): void
    {
        $tag = Tag::factory()->create();
        $media = Media::query()->create([
            'model_type' => $tag->getMorphClass(),
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'already-webp',
            'file_name' => 'already.webp',
            'mime_type' => 'image/webp',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        File::ensureDirectoryExists(dirname($this->mediaPath($media, 'already.webp')));
        File::put($this->mediaPath($media, 'already.webp'), 'webp-bytes');

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('converted=0')
            ->assertSuccessful();

        $media->refresh();
        $this->assertSame('already.webp', $media->file_name);
        $this->assertFileExists($this->mediaPath($media, 'already.webp'));
    }

    #[Test]
    public function id_and_limit_options_target_subset(): void
    {
        $first = $this->createPngMedia('first.png');
        $second = $this->createPngMedia('second.png');
        $third = $this->createPngMedia('third.png');

        $this->artisan('media:convert-png-originals-to-webp', [
            '--id' => [$first->id, $second->id, $third->id],
            '--limit' => 1,
        ])->assertSuccessful();

        $first->refresh();
        $second->refresh();
        $third->refresh();

        $convertedCount = collect([$first, $second, $third])
            ->filter(fn (Media $media): bool => str_ends_with($media->file_name, '.webp'))
            ->count();

        $this->assertSame(1, $convertedCount);
        $this->assertSame(2, collect([$first, $second, $third])
            ->filter(fn (Media $media): bool => str_ends_with($media->file_name, '.png'))
            ->count());
    }

    #[Test]
    public function missing_file_is_reported_as_failed_without_aborting(): void
    {
        $missing = $this->createPngMediaRow('missing.png');
        $present = $this->createPngMedia('present.png');
        $present->generated_conversions = [];
        $present->save();

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('Failed media ['.$missing->id.']')
            ->expectsOutputToContain('Converted media ['.$present->id.']')
            ->assertFailed();

        $missing->refresh();
        $present->refresh();

        $this->assertSame('missing.png', $missing->file_name);
        $this->assertSame('present.webp', $present->file_name);
    }

    #[Test]
    public function it_removes_orphan_media_library_original_responsive_pngs(): void
    {
        $media = $this->createPngMedia('with-orphan.png');
        $media->generated_conversions = [];
        $media->save();

        $orphanPath = $this->mediaPath($media, 'responsive-images/with-orphan___media_library_original_128_72.png');
        File::ensureDirectoryExists(dirname($orphanPath));
        File::put($orphanPath, 'orphan');

        $keptWebp = $this->mediaPath($media, 'responsive-images/with-orphan___webp_128_72.webp');
        File::put($keptWebp, 'keep-me');

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('orphans=1')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($orphanPath);
        $this->assertFileExists($keptWebp);
        $this->assertFileExists($this->mediaPath($media, 'with-orphan.webp'));
    }

    #[Test]
    public function it_restores_missing_original_from_conversion_webp(): void
    {
        $media = $this->createWebpMedia('gone.webp', width: 200, height: 112);
        $media->name = 'gone';
        $media->generated_conversions = ['webp' => true];
        $media->save();

        $conversionPath = $this->mediaPath($media, 'conversions/gone-webp.webp');
        File::ensureDirectoryExists(dirname($conversionPath));
        $this->writeWebpFixture($conversionPath, 64, 36);
        $conversionBytes = File::get($conversionPath);

        $this->assertFileDoesNotExist($this->mediaPath($media, 'gone.webp'));

        $this->artisan('media:convert-png-originals-to-webp')
            ->expectsOutputToContain('Converted')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame('gone.webp', $media->file_name);
        $this->assertSame(File::size($conversionPath), $media->size);
        $this->assertSame(64, $media->getCustomProperty('width'));
        $this->assertSame(36, $media->getCustomProperty('height'));
        $this->assertSame($conversionBytes, File::get($this->mediaPath($media, 'gone.webp')));
    }

    #[Test]
    public function it_finds_original_by_media_name_when_registered_path_is_wrong(): void
    {
        $media = $this->createWebpMedia('wrong-name.webp', width: 200, height: 112);
        $media->name = 'real-stem';
        $media->file_name = 'wrong-name.webp';
        $media->generated_conversions = ['webp' => true];
        $media->save();

        $realOriginal = $this->mediaPath($media, 'real-stem.webp');
        $this->writeWebpFixture($realOriginal, 200, 112);
        $bytesBefore = File::size($realOriginal);
        $media->size = $bytesBefore;
        $media->save();

        $conversionPath = $this->mediaPath($media, 'conversions/real-stem-webp.webp');
        File::ensureDirectoryExists(dirname($conversionPath));
        $this->writeWebpFixture($conversionPath, 64, 36);
        $this->assertLessThan($bytesBefore, File::size($conversionPath));

        $this->artisan('media:convert-png-originals-to-webp')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame('real-stem.webp', $media->file_name);
        $this->assertFileDoesNotExist($realOriginal === $this->mediaPath($media, 'real-stem.webp')
            ? $this->mediaPath($media, 'wrong-name.webp')
            : $realOriginal);
        $this->assertFileExists($this->mediaPath($media, 'real-stem.webp'));
        $this->assertSame(File::size($conversionPath), $media->size);
    }

    private function createPngMedia(string $fileName, int $width = 32, int $height = 18): Media
    {
        $media = $this->createPngMediaRow($fileName, $width, $height);
        $path = $this->mediaPath($media, $fileName);
        File::ensureDirectoryExists(dirname($path));
        $this->writePngFixture($path, $width, $height);

        $media->size = File::size($path);
        $media->save();

        return $media->refresh();
    }

    private function createWebpMedia(string $fileName, int $width = 32, int $height = 18): Media
    {
        $tag = Tag::factory()->create();

        $media = Media::query()->create([
            'model_type' => $tag->getMorphClass(),
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/webp',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [
                'width' => $width,
                'height' => $height,
            ],
            'generated_conversions' => ['webp' => true],
            'responsive_images' => ['webp' => ['urls' => [], 'base64svg' => '']],
            'order_column' => 1,
        ]);

        File::ensureDirectoryExists(dirname($this->mediaPath($media, $fileName)));

        return $media;
    }

    private function createPngMediaRow(string $fileName, int $width = 32, int $height = 18): Media
    {
        $tag = Tag::factory()->create();

        return Media::query()->create([
            'model_type' => $tag->getMorphClass(),
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/png',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [
                'width' => $width,
                'height' => $height,
            ],
            'generated_conversions' => ['webp' => true],
            'responsive_images' => ['webp' => ['urls' => [], 'base64svg' => '']],
            'order_column' => 1,
        ]);
    }

    private function mediaPath(Media $media, string $relative): string
    {
        return $this->diskRoot
            .DIRECTORY_SEPARATOR.'media'
            .DIRECTORY_SEPARATOR.$media->id
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function writePngFixture(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);

        $color = imagecolorallocate($image, 40, 120, 200);
        $this->assertNotFalse($color);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);

        $this->assertTrue(imagepng($image, $path));
        imagedestroy($image);
    }

    private function writeWebpFixture(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);

        $color = imagecolorallocate($image, 20, 180, 90);
        $this->assertNotFalse($color);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);

        // Add noise so larger canvases stay larger on disk after WebP encode.
        for ($i = 0; $i < max(10, (int) ($width * $height / 8)); $i++) {
            $noise = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            if ($noise === false) {
                continue;
            }
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $noise);
        }

        $this->assertTrue(imagewebp($image, $path, 90));
        imagedestroy($image);
    }
}
