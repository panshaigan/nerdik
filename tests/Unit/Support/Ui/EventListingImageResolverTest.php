<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Enums\EventLogoSource;
use App\Models\ActivityType;
use App\Models\Event;
use App\Support\Events\EventDefaultImageCatalog;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SeedsListingDefaultMedia;
use Tests\TestCase;

final class EventListingImageResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsListingDefaultMedia;

    #[Test]
    public function it_uses_seeded_event_listing_default_media(): void
    {
        $this->seedListingDefaultMedia();

        $picture = app(EventListingImageResolver::class)->resolve();

        $this->assertNotNull($picture->sources);
        $this->assertNull($picture->staticUrl);
    }

    #[Test]
    public function it_falls_back_to_warhammer_when_no_seeded_media(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $picture = app(EventListingImageResolver::class)->resolve();

        $this->assertNull($picture->sources);
        $this->assertStringContainsString(
            ListingCardPicture::GLOBAL_FALLBACK_ASSET,
            (string) $picture->staticUrl,
        );
    }

    #[Test]
    public function seeded_event_default_is_attached_via_listing_role(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpgType);

        $fixture = 'images/listing/test-event-default.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixture));

        app(AttachModelMediaFromPublic::class)(
            $rpgType,
            [$fixture],
            ['listing_role' => EventListingImageResolver::LISTING_ROLE],
        );

        $picture = app(EventListingImageResolver::class)->resolve();

        $this->assertNotNull($picture->sources);
    }

    #[Test]
    public function it_uses_uploaded_logo_path_when_event_logo_source_is_upload(): void
    {
        Storage::fake('public');
        $path = 'event-logos/test-upload.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('upload.jpg')->getContent());

        $event = Event::factory()->create([
            'logo_source' => EventLogoSource::Upload,
            'logo_path' => $path,
            'listing_media_id' => null,
        ]);

        $picture = app(EventListingImageResolver::class)->resolve($event);

        $this->assertNull($picture->sources);
        $this->assertNotNull($picture->staticUrl);
        $this->assertStringContainsString($path, (string) $picture->staticUrl);
    }

    #[Test]
    public function it_uses_selected_default_media_for_event(): void
    {
        $this->seedListingDefaultMedia();

        $mediaId = (int) app(EventDefaultImageCatalog::class)->availableMediaIds()[0];

        $event = Event::factory()->create([
            'logo_source' => EventLogoSource::Default,
            'listing_media_id' => $mediaId,
            'logo_path' => null,
        ]);

        $picture = app(EventListingImageResolver::class)->resolve($event);

        $this->assertNotNull($picture->sources);
        $this->assertNull($picture->staticUrl);
    }
}
