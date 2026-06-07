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
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class AttachListingDefaultsFromSeederLibraryTest extends TestCase
{
    use AssertsResponsiveMedia;
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
    public function it_attaches_activity_defaults_and_event_listing_catalog_media(): void
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

        $activityDefault = $rpgType->getMedia('images')->first();
        $this->assertNotNull($activityDefault);
        $this->assertStringContainsString('default_activity_type_01.jpg', (string) $activityDefault->getCustomProperty('seed_source'));
        $this->assertMediaHasResponsiveConversions($activityDefault);

        $eventDefault = $rpgType->getMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION)->first();
        $this->assertNotNull($eventDefault);
        $this->assertStringContainsString('default_event.jpg', (string) $eventDefault->getCustomProperty('seed_source'));
        $this->assertMediaHasResponsiveConversions($eventDefault);
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
