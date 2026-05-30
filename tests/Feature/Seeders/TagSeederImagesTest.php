<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagSeederImagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function game_and_setting_tags_receive_default_seed_image(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);

        Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $genreTag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        Tag::factory()->create(['tag_category_id' => $settingCategory->id]);

        $seeder = new TagSeeder;
        $seeder->seedDefaultTagImages();

        $tagsWithImages = Tag::query()
            ->whereHas('tagCategory', fn ($query) => $query->whereIn('key', [
                TagCategory::KEY_GAME,
                TagCategory::KEY_SETTING,
            ]))
            ->get();

        $this->assertCount(2, $tagsWithImages);
        $this->assertCount(0, $genreTag->refresh()->getMedia('images'));

        foreach ($tagsWithImages as $tag) {
            $media = $tag->getFirstMedia('images');
            $this->assertNotNull($media, "Tag #{$tag->id} should have a seed image.");
            $this->assertSame('images/tag-game/warhammer.jpg', $media->getCustomProperty('seed_source'));
            $this->assertTrue($media->hasGeneratedConversion('webp'));
            $this->assertNotEmpty($media->responsive_images);
        }
    }

    #[Test]
    public function rpg_activity_type_and_event_listing_defaults_are_seeded(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $seeder = new TagSeeder;
        $seeder->seedListingImages();

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpgType);

        $typeMedia = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') !== EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($typeMedia);
        $this->assertSame('images/listing/activity-type-rpg.jpg', $typeMedia->getCustomProperty('seed_source'));

        $eventDefault = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') === EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($eventDefault);
        $this->assertSame('images/listing/event-default.jpg', $eventDefault->getCustomProperty('seed_source'));
    }

    #[Test]
    public function other_category_tags_do_not_receive_default_seed_image(): void
    {
        $otherCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_OTHER]);
        $tag = Tag::factory()->create(['tag_category_id' => $otherCategory->id]);

        $seeder = new TagSeeder;
        $seeder->seedDefaultTagImages();

        $this->assertCount(0, $tag->refresh()->getMedia('images'));
    }
}
