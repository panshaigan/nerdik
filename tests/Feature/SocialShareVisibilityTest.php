<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Activities\ShowActivity;
use App\Livewire\Browse\BrowseActivities;
use App\Livewire\Browse\BrowseEvents;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SocialShareVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_event_show_includes_share_menu_when_not_cancelled(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Guest Share Event',
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="share-menu"');
    }

    public function test_guest_event_show_hides_share_menu_when_cancelled(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Cancelled Share Event',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertDontSeeHtml('data-ui="share-menu"');
    }

    public function test_guest_activity_show_includes_share_menu_when_not_cancelled(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(4)->setSecond(0);
        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'name' => 'Guest Share Activity',
        ]);

        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertSeeHtml('data-ui="share-menu"');
    }

    public function test_guest_activity_show_hides_share_menu_when_cancelled(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(4)->setSecond(0);
        $activity = Activity::factory()->selfHosted()->cancelled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'name' => 'Cancelled Share Activity',
        ]);

        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertDontSeeHtml('data-ui="share-menu"');
    }

    public function test_event_preview_modal_includes_share_menu(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => 'Preview Share Event',
        ]);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->call('openListingEventPreview', $event->id)
            ->assertSeeHtml('data-ui="share-menu"');
    }

    public function test_activity_preview_modal_includes_share_menu(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Preview Share Activity',
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::withoutLazyLoading()
            ->test(BrowseActivities::class)
            ->call('openListingActivityPreview', $activity->id)
            ->assertSeeHtml('data-ui="share-menu"');
    }
}
