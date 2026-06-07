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

final class MigrateMediaStoragePathsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskRoot = storage_path('framework/testing/media-migrate-'.uniqid());
        File::ensureDirectoryExists($this->diskRoot);

        config([
            'filesystems.disks.public.root' => $this->diskRoot,
            'media.storage_path_prefix' => 'media',
        ]);

        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    #[Test]
    public function it_moves_legacy_media_directories_into_configured_prefix(): void
    {
        $tag = Tag::factory()->create();

        $media = Media::query()->create([
            'model_type' => $tag->getMorphClass(),
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'legacy',
            'file_name' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $legacyPath = $this->diskRoot.DIRECTORY_SEPARATOR.$media->id;
        $prefixedPath = $this->diskRoot.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$media->id;

        File::ensureDirectoryExists($legacyPath);
        File::put($legacyPath.DIRECTORY_SEPARATOR.'legacy.jpg', 'legacy-content');

        $this->artisan('media:migrate-storage-paths')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($legacyPath.DIRECTORY_SEPARATOR.'legacy.jpg');
        $this->assertFileExists($prefixedPath.DIRECTORY_SEPARATOR.'legacy.jpg');
    }

    #[Test]
    public function it_skips_media_already_stored_under_prefix(): void
    {
        $tag = Tag::factory()->create();

        $media = Media::query()->create([
            'model_type' => $tag->getMorphClass(),
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'prefixed',
            'file_name' => 'prefixed.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $legacyPath = $this->diskRoot.DIRECTORY_SEPARATOR.$media->id;
        $prefixedPath = $this->diskRoot.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$media->id;

        File::ensureDirectoryExists($legacyPath);
        File::put($legacyPath.DIRECTORY_SEPARATOR.'legacy.jpg', 'legacy-content');
        File::ensureDirectoryExists($prefixedPath);
        File::put($prefixedPath.DIRECTORY_SEPARATOR.'prefixed.jpg', 'ok');

        $this->artisan('media:migrate-storage-paths')
            ->expectsOutputToContain('skipped=1')
            ->assertSuccessful();

        $this->assertFileExists($prefixedPath.DIRECTORY_SEPARATOR.'prefixed.jpg');
    }
}
