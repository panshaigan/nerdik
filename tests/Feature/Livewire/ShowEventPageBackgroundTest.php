<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\EventLogoSource;
use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Support\Events\EventDefaultImageCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SeedsListingDefaultMedia;
use Tests\TestCase;

final class ShowEventPageBackgroundTest extends TestCase
{
    use RefreshDatabase;
    use SeedsListingDefaultMedia;

    #[Test]
    public function it_renders_blurred_page_background_with_resolved_cover_picture(): void
    {
        $this->seedListingDefaultMedia();

        $mediaId = (int) app(EventDefaultImageCatalog::class)->availableMediaIds()[0];

        $event = Event::factory()->create([
            'logo_source' => EventLogoSource::Default,
            'listing_media_id' => $mediaId,
            'logo_path' => null,
        ]);

        $html = Livewire::test(ShowEvent::class, ['event' => $event])->html();

        $this->assertStringContainsString('data-ui="event-show-page-background"', $html);
        $this->assertStringContainsString('blur-md', $html);
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
    }
}
