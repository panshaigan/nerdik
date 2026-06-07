<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SeedTagImagesReplaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $libraryFilesToRemove = [];

    protected function tearDown(): void
    {
        foreach ($this->libraryFilesToRemove as $path) {
            if (File::isFile($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function replace_flag_removes_existing_seed_media_before_re_attaching(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Replace Fantasy',
            'slug' => 'replace-fantasy',
        ]);

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $firstFilename = "{$tag->id}_replace_fantasy.jpg";
        $this->placeLibraryImage('Genres', $firstFilename, $fixture);

        $this->artisan('tags:seed-images')->assertSuccessful();
        $firstMediaId = (int) $tag->refresh()->getFirstMedia('images')?->id;
        $this->assertNotNull($firstMediaId);

        $this->artisan('tags:seed-images', ['--replace' => true])->assertSuccessful();

        $tag->refresh();
        $this->assertCount(1, $tag->getMedia('images'));
        $this->assertNotSame($firstMediaId, (int) $tag->getFirstMedia('images')?->id);
        $this->assertStringContainsString($firstFilename, (string) $tag->getFirstMedia('images')?->getCustomProperty('seed_source'));
    }

    #[Test]
    public function replace_flag_clears_event_listing_catalog_before_re_seeding(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $this->artisan('tags:seed-images')->assertSuccessful();

        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpg);
        $this->assertGreaterThan(0, $rpg->getMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION)->count());

        $this->artisan('tags:seed-images', ['--replace' => true])->assertSuccessful();

        $rpg->refresh();
        $this->assertGreaterThan(0, $rpg->getMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION)->count());
    }

    private function placeLibraryImage(string $categoryFolder, string $filename, string $sourcePath): void
    {
        $directory = database_path('seeders/tag_images/'.$categoryFolder);
        File::ensureDirectoryExists($directory);

        $destination = $directory.'/'.$filename;
        copy($sourcePath, $destination);
        $this->libraryFilesToRemove[] = $destination;
    }
}
