<?php

namespace Tests\Feature;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListingCardEventPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_listing_card_uses_event_preview_button_instead_of_navigate_link(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $eventId = (int) $event->id;

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class)
            ->assertSeeHtml('wire:click="openListingEventPreview('.$eventId.')"')
            ->assertSeeHtml('href="'.route('events.show', $event).'"')
            ->assertDontSeeHtml('data-ui="event-card-link"');
    }

    public function test_listing_event_card_does_not_show_host_badge(): void
    {
        $owner = User::factory()->create(['nickname' => 'Event Card Host Only']);
        $viewer = User::factory()->create();
        Event::factory()->public()->create(['created_by' => $owner->id]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(BrowseEvents::class)
            ->assertDontSee('Event Card Host Only');
    }

    public function test_listing_event_card_shows_edit_for_admin_who_is_not_owner(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        Event::factory()->public()->create(['created_by' => $owner->id]);

        Livewire::withoutLazyLoading()
            ->actingAs($admin)
            ->test(BrowseEvents::class)
            ->assertSeeHtml('data-ui="event-card-edit"');
    }

    public function test_listing_event_card_shows_enrollment_open_badge_when_window_is_active(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class)
            ->assertSee(__('ui.events.enrollment_window_active_badge'));
    }

    public function test_listing_event_card_hides_enrollment_open_badge_when_window_is_closed(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class)
            ->assertDontSeeHtml('data-ui="event-card-enrollment-open"');
    }

    public function test_open_listing_event_preview_shows_description_and_details_link(): void
    {
        $host = User::factory()->create(['nickname' => 'Event Preview Host']);
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $host->id,
            'organization_id' => null,
            'description' => 'Unique event preview body for listing modal',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertSet('eventPreviewModalOpen', true)
            ->assertSet('previewEventId', $event->id)
            ->assertSee('Unique event preview body for listing modal')
            ->assertSeeHtml('data-ui="listing-event-preview-host"')
            ->assertSeeHtml('wire:key="user-badge-contact-'.$host->id.'-0-0-late-0-listing-event-preview-'.$event->id.'"')
            ->assertSeeHtml('href="'.route('events.show', $event).'"')
            ->assertSee(__('ui.events.show_details'))
            ->assertSeeHtml('data-ui="overlay-sheet"')
            ->assertSeeHtml('data-ui="listing-event-preview-actions"');
    }

    public function test_open_listing_event_preview_shows_short_propose_cta_when_eligible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $host->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertViewHas('previewEventCanProposeActivity', true)
            ->assertSee(__('ui.events.propose_activity_short'))
            ->assertSeeHtml('data-ui="listing-event-preview-propose"')
            ->assertSee('proposal_event_id='.$event->id, false)
            ->assertDontSeeHtml('data-ui="listing-event-preview-propose-guest"');
    }

    public function test_open_listing_event_preview_guest_propose_cta_links_to_login(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $host = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $host->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        $planReturn = route('events.show', ['event' => $event, 'tab' => 'plan'], false);
        $guestProposeUrl = login_url($planReturn);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertViewHas('previewEventCanProposeActivity', true)
            ->assertSee(__('ui.events.propose_activity_short'))
            ->assertSeeHtml('data-ui="listing-event-preview-propose-guest"')
            ->assertSee($guestProposeUrl, false)
            ->assertDontSeeHtml('data-ui="listing-event-preview-propose"');
    }

    public function test_open_listing_event_preview_hides_propose_cta_after_event_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'UTC'));
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $host->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertViewHas('previewEventCanProposeActivity', false)
            ->assertDontSeeHtml('data-ui="listing-event-preview-propose"')
            ->assertDontSeeHtml('data-ui="listing-event-preview-propose-guest"');
    }

    public function test_my_events_browse_opens_event_preview_modal(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'description' => 'My events preview description',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseEvents::class, [
                'include_past_events' => true,
                'only_events' => true,
                'only_mine' => true,
            ])
            ->call('openListingEventPreview', $event->id)
            ->assertSet('eventPreviewModalOpen', true)
            ->assertSee('My events preview description')
            ->assertSeeHtml('data-ui="listing-event-preview-modal"')
            ->assertSeeHtml('data-ui="overlay-sheet"')
            ->assertSeeHtml('data-ui="listing-event-preview-actions"');
    }
}
