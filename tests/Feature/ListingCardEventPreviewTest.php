<?php

namespace Tests\Feature;

use App\Livewire\Browse\BrowseEvents;
use App\Livewire\Me\MyEvents;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListingCardEventPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_card_uses_event_preview_button_instead_of_navigate_link(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $eventId = (int) $event->id;

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class)
            ->assertSeeHtml('wire:click="openListingEventPreview('.$eventId.')"')
            ->assertSeeHtml('data-ui="event-card-open-preview"')
            ->assertDontSeeHtml('data-ui="event-card-link"');
    }

    public function test_open_listing_event_preview_shows_description_and_details_link(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'description' => 'Unique event preview body for listing modal',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertSet('eventPreviewModalOpen', true)
            ->assertSet('previewEventId', $event->id)
            ->assertSee('Unique event preview body for listing modal')
            ->assertSeeHtml('href="'.route('events.show', $event).'"')
            ->assertSee(__('ui.events.show_details'));
    }

    public function test_my_events_page_opens_event_preview_modal(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'description' => 'My events preview description',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(MyEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertSet('eventPreviewModalOpen', true)
            ->assertSee('My events preview description')
            ->assertSeeHtml('data-ui="listing-event-preview-modal"');
    }
}
