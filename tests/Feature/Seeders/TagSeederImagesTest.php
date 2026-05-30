<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Actions\Seeders\AttachTagMediaFromSeederLibrary;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagSeederImagesTest extends TestCase
{
    use RefreshDatabase;

    private string $libraryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->libraryRoot = storage_path('framework/testing/tag-library-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->libraryRoot);

        parent::tearDown();
    }

    #[Test]
    public function seed_tag_images_from_library_scans_genres_games_and_settings(): void
    {
        config(['media.test_profile' => 'full']);

        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);

        $fantasy = $this->createTagWithTranslation($genreCategory, 'Fantasy', 'fantasy');
        $dnd = $this->createTagWithTranslation($gameCategory, 'Dungeons & Dragons', 'dungeons-dragons');
        $forgottenRealms = $this->createTagWithTranslation($settingCategory, 'Forgotten Realms', 'forgotten-realms');

        $fixture = base_path('tests/fixtures/tag-sample.jpg');

        File::ensureDirectoryExists($this->libraryRoot.'/Genres');
        File::ensureDirectoryExists($this->libraryRoot.'/Games');
        File::ensureDirectoryExists($this->libraryRoot.'/Settings');

        copy($fixture, $this->libraryRoot.'/Genres/fantasy.jpg');
        copy($fixture, $this->libraryRoot.'/Games/dungeons-dragons.jpg');
        copy($fixture, $this->libraryRoot.'/Settings/forgotten-realms.jpg');

        $attach = app(AttachTagMediaFromSeederLibrary::class);
        $attach($this->libraryRoot.'/Genres', TagCategory::KEY_GENRE);
        $attach($this->libraryRoot.'/Games', TagCategory::KEY_GAME);
        $attach($this->libraryRoot.'/Settings', TagCategory::KEY_SETTING);

        $this->assertCount(1, $fantasy->refresh()->getMedia('images'));
        $this->assertCount(1, $dnd->refresh()->getMedia('images'));
        $this->assertCount(1, $forgottenRealms->refresh()->getMedia('images'));
    }

    #[Test]
    public function rpg_activity_type_and_event_listing_defaults_are_seeded(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $seeder = new TagSeeder;
        $seeder->seedListingImages();

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpgType);

        $typeMedia = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') !== EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($typeMedia);
        $this->assertStringContainsString('Default/Activity/default_activity_type_01.png', $typeMedia->getCustomProperty('seed_source'));

        $eventDefault = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') === EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($eventDefault);
        $this->assertStringContainsString('Default/Event/default_event.png', $eventDefault->getCustomProperty('seed_source'));
    }

    private function createTagWithTranslation(TagCategory $category, string $label, string $slug): Tag
    {
        $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => $label,
            'slug' => $slug,
        ]);

        return $tag;
    }
}
