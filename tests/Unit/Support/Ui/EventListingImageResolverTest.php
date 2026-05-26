<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Models\ActivityType;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventListingImageResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_seeded_event_listing_default_media(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $this->seed(TagSeeder::class);

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
}
