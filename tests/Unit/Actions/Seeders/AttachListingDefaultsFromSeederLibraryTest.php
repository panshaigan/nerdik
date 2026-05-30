<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachListingDefaultsFromSeederLibrary;
use App\Models\ActivityType;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AttachListingDefaultsFromSeederLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $defaultDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultDirectory = storage_path('framework/testing/listing-defaults-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->defaultDirectory);

        parent::tearDown();
    }

    #[Test]
    public function it_attaches_activity_defaults_to_rpg_and_event_defaults_with_listing_role(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        File::ensureDirectoryExists($this->defaultDirectory.'/Activity');
        File::ensureDirectoryExists($this->defaultDirectory.'/Event');
        copy($fixture, $this->defaultDirectory.'/Activity/default_activity_type_01.jpg');
        copy($fixture, $this->defaultDirectory.'/Event/default_event.jpg');

        app(AttachListingDefaultsFromSeederLibrary::class)($this->defaultDirectory);

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpgType);

        $activityDefault = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') !== EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($activityDefault);
        $this->assertStringContainsString('default_activity_type_01.jpg', $activityDefault->getCustomProperty('seed_source'));

        $eventDefault = $rpgType->getMedia('images')
            ->first(fn ($media) => $media->getCustomProperty('listing_role') === EventListingImageResolver::LISTING_ROLE);
        $this->assertNotNull($eventDefault);
        $this->assertStringContainsString('default_event.jpg', $eventDefault->getCustomProperty('seed_source'));
    }

    #[Test]
    public function it_attaches_activity_folder_images_to_matching_activity_type_slug(): void
    {
        config(['media.test_profile' => 'full']);

        $this->seed(ActivityTypeSeeder::class);

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $wargameDirectory = $this->defaultDirectory.'/Activity/wargame';
        File::ensureDirectoryExists($wargameDirectory);
        copy($fixture, $wargameDirectory.'/default.jpg');

        app(AttachListingDefaultsFromSeederLibrary::class)($this->defaultDirectory);

        $wargameType = ActivityType::findBySlug(ActivityType::SLUG_WARGAME);
        $this->assertNotNull($wargameType);
        $this->assertCount(1, $wargameType->getMedia('images'));
    }
}
