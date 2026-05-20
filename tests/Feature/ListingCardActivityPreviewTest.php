<?php

namespace Tests\Feature;

use App\Livewire\Browse\BrowseActivities;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListingCardActivityPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_card_uses_preview_button_instead_of_navigate_link(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'description' => 'Listing card preview description marker',
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $activityId = (int) $activity->id;

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseActivities::class)
            ->assertSeeHtml('wire:click="openListingActivityPreview('.$activityId.')"')
            ->assertSeeHtml('data-ui="activity-card-open-preview"')
            ->assertDontSeeHtml('data-ui="activity-card-link"');
    }

    public function test_open_listing_activity_preview_shows_description_and_details_link(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'description' => 'Unique preview body for listing modal',
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(BrowseActivities::class)
            ->call('openListingActivityPreview', $activity->id)
            ->assertSet('activityPreviewModalOpen', true)
            ->assertSet('previewActivityId', $activity->id)
            ->assertSee('Unique preview body for listing modal')
            ->assertSeeHtml('href="'.route('activities.show', $activity).'"')
            ->assertSee(__('ui.activities.show_details'));
    }

    public function test_listing_activity_preview_hides_participation_tab_and_join_actions(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'requires_approval' => false,
            'max_participants' => 4,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'max_activities_per_user' => 2,
            'max_allowed_participants_per_activity' => 4,
            'accumulative_activities' => false,
            'created_by' => $owner->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(BrowseActivities::class)
            ->call('openListingActivityPreview', $activity->id)
            ->assertDontSeeHtml('data-ui="event-activity-preview-tab-participation"')
            ->assertDontSeeHtml('wire:target="joinPreviewActivity"')
            ->assertDontSeeHtml('wire:target="leavePreviewActivity"');
    }

    public function test_dashboard_feed_opens_activity_preview_modal(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'description' => 'Dashboard feed preview description',
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(Dashboard::class)
            ->call('openListingActivityPreview', $activity->id)
            ->assertSet('activityPreviewModalOpen', true)
            ->assertSee('Dashboard feed preview description');
    }
}
