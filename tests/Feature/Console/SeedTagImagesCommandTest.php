<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackfillMediaThumbnailsCommand;
use App\Console\Commands\SeedTagImagesCommand;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class SeedTagImagesCommandTest extends TestCase
{
    use AssertsResponsiveMedia;
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
    public function command_attaches_tag_images_from_library(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = $this->createTagWithTranslation($genreCategory, 'Command Fantasy', 'command-fantasy');

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $libraryFilename = "{$tag->id}_command_fantasy.jpg";

        $this->placeLibraryImage('Genres', $libraryFilename, $fixture);
        $this->assertFileExists(database_path('seeders/tag_images/Genres/'.$libraryFilename));

        $this->artisan('tags:seed-images')
            ->expectsOutputToContain('Tag and listing images attached from seeder library.')
            ->assertSuccessful();

        $this->assertTagHasLibraryImage($tag, $libraryFilename);
    }

    #[Test]
    public function command_generates_conversions_and_responsive_images_for_seeded_media(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = $this->createTagWithTranslation($genreCategory, 'Responsive Fantasy', 'responsive-fantasy');

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $libraryFilename = "{$tag->id}_responsive_fantasy.jpg";

        $this->placeLibraryImage('Genres', $libraryFilename, $fixture);

        $this->artisan('tags:seed-images')
            ->expectsOutputToContain('Conversions and responsive thumbnails generated for seeded media.')
            ->expectsOutputToContain('php artisan media:backfill-thumbnails')
            ->assertSuccessful();

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);
        $this->assertMediaHasResponsiveConversions($media);
        $this->assertTrue($media->hasResponsiveImages('webp'));
    }

    #[Test]
    public function seed_command_regenerate_options_include_force(): void
    {
        $this->assertSame(
            BackfillMediaThumbnailsCommand::REGENERATE_OPTIONS,
            [
                '--only-missing' => true,
                '--with-responsive-images' => true,
                '--force' => true,
                '--no-interaction' => true,
            ],
        );
        $this->assertStringContainsString('--force', SeedTagImagesCommand::REGENERATE_MISSING_COMMAND);
    }

    #[Test]
    public function command_attaches_listing_defaults_for_activity_types(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $this->artisan('tags:seed-images')->assertSuccessful();

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpgType);

        $typeMedia = $rpgType->getMedia('images')->first();
        $this->assertNotNull($typeMedia);
        $this->assertStringContainsString('Default/Activity/default_activity_type_01.png', $typeMedia->getCustomProperty('seed_source'));

        $eventDefault = $rpgType->getMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION)->first();
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

    private function placeLibraryImage(string $categoryFolder, string $filename, string $sourcePath): void
    {
        $directory = database_path('seeders/tag_images/'.$categoryFolder);
        File::ensureDirectoryExists($directory);

        $destination = $directory.'/'.$filename;
        copy($sourcePath, $destination);
        $this->libraryFilesToRemove[] = $destination;
    }

    private function assertTagHasLibraryImage(Tag $tag, string $filename): void
    {
        $tag->refresh();

        $this->assertTrue(
            $tag->getMedia('images')->contains(
                fn ($media) => str_contains((string) $media->getCustomProperty('seed_source'), $filename),
            ),
            "Expected tag {$tag->id} to have an image from {$filename}.",
        );
    }
}
