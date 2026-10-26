<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabActivityInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_and_remove_activity_interest_updates_star_button_without_reload(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $user->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $component = Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, [
                'eventId' => $event->id,
                'shellInterestedActivityIds' => [],
            ])
            ->assertSeeHtml('data-ui="event-show-slot-interest-add"')
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-remove"')
            ->assertSeeHtml('data-ui="event-show-slot-interest-count"')
            ->assertSeeHtml('data-count="0"')
            ->assertSeeHtml('data-ui="event-show-slot-open-details"')
            ->assertSeeHtml('data-ui="event-show-slot-edit"')
            ->assertSeeHtml('href="'.route('activities.show', $activity).'"');

        $component
            ->call('addActivityInterest', $activity->id)
            ->assertDispatched('event-show-shell-refresh')
            ->assertSeeHtml('data-ui="event-show-slot-interest-remove"')
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-add"')
            ->assertSeeHtml('data-count="1"');

        $this->assertTrue(
            $user->fresh()->interestedActivities()->whereKey($activity->id)->exists()
        );
        $this->assertTrue(
            $user->fresh()->interestedEvents()->whereKey($event->id)->exists()
        );

        $component
            ->call('removeActivityInterest', $activity->id)
            ->assertSeeHtml('data-ui="event-show-slot-interest-add"')
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-remove"')
            ->assertSeeHtml('data-count="0"');

        $this->assertFalse(
            $user->fresh()->interestedActivities()->whereKey($activity->id)->exists()
        );
    }

    public function test_slot_interest_star_shows_full_follower_count(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $user->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        User::factory()
            ->count(12)
            ->create()
            ->each(fn (User $follower) => $follower->interestedActivities()->attach($activity->id));

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, [
                'eventId' => $event->id,
                'shellInterestedActivityIds' => [],
            ])
            ->assertSeeHtml('data-ui="event-show-slot-interest-count"')
            ->assertSeeHtml('data-count="12"')
            ->assertDontSeeHtml('9+');
    }
}
