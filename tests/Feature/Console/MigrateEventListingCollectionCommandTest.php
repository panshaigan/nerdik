<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\ActivityType;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class MigrateEventListingCollectionCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_legacy_listing_role_media_to_event_listing_collection(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpg);

        $media = Media::query()->create([
            'model_type' => $rpg->getMorphClass(),
            'model_id' => $rpg->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'legacy-event',
            'file_name' => 'legacy-event.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => ['listing_role' => EventListingImageResolver::LISTING_ROLE],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $this->artisan('media:migrate-event-listing-collection')
            ->assertSuccessful();

        $media->refresh();

        $this->assertSame(EventListingImageResolver::EVENT_LISTING_COLLECTION, $media->collection_name);
    }
}
