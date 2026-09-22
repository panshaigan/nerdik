<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Catalog;

use App\Actions\Events\StoreUploadedEventLogo;
use App\Enums\EventLogoSource;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\User;
use App\Support\Catalog\CatalogSeriesClosestEventResolver;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogSeriesClosestEventResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_nearest_upcoming_event_cover_for_series_tile(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);

        $past = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'logo_source' => EventLogoSource::Upload,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addHours(3),
        ]);
        $upcomingSoon = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'logo_source' => EventLogoSource::Upload,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'logo_source' => EventLogoSource::Upload,
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(3),
        ]);

        app(StoreUploadedEventLogo::class)($past, UploadedFile::fake()->image('past.jpg', 800, 450));
        app(StoreUploadedEventLogo::class)($upcomingSoon, UploadedFile::fake()->image('soon.jpg', 800, 450));

        $covers = app(CatalogSeriesClosestEventResolver::class)
            ->coverPicturesBySeriesId(collect([$series]));

        $this->assertArrayHasKey((int) $series->id, $covers);
        $this->assertInstanceOf(ListingCardPicture::class, $covers[(int) $series->id]);
        $this->assertTrue($covers[(int) $series->id]->hasDisplayableImage());
    }
}
