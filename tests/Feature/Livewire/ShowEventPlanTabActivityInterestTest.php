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
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-remove"');

        $component
            ->call('addActivityInterest', $activity->id)
            ->assertSeeHtml('data-ui="event-show-slot-interest-remove"')
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-add"');

        $this->assertTrue(
            $user->fresh()->interestedActivities()->whereKey($activity->id)->exists()
        );

        $component
            ->call('removeActivityInterest', $activity->id)
            ->assertSeeHtml('data-ui="event-show-slot-interest-add"')
            ->assertDontSeeHtml('data-ui="event-show-slot-interest-remove"');

        $this->assertFalse(
            $user->fresh()->interestedActivities()->whereKey($activity->id)->exists()
        );
    }
}
