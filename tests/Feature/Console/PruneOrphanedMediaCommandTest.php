<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class PruneOrphanedMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_media_without_a_parent_model(): void
    {
        $orphan = Media::query()->create([
            'model_type' => Tag::class,
            'model_id' => 999999,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'orphan',
            'file_name' => 'orphan.jpg',
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

        $this->artisan('media:prune-orphans')
            ->assertSuccessful();

        $this->assertNull(Media::query()->find($orphan->id));
    }

    #[Test]
    public function dry_run_does_not_delete_orphan_media(): void
    {
        $orphan = Media::query()->create([
            'model_type' => Tag::class,
            'model_id' => 999999,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'orphan',
            'file_name' => 'orphan.jpg',
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

        $this->artisan('media:prune-orphans', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertNotNull(Media::query()->find($orphan->id));
    }
}
