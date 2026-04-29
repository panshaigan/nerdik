<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseInterestToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_event_interest_adds_and_removes_interest(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();

        $component = Livewire::actingAs($user)->test(BrowseEvents::class);

        $component->call('toggleEventInterest', $event->id);
        $this->assertTrue($user->fresh()->interestedEvents()->whereKey($event->id)->exists());

        $component->call('toggleEventInterest', $event->id);
        $this->assertFalse($user->fresh()->interestedEvents()->whereKey($event->id)->exists());
    }

    public function test_toggle_activity_interest_adds_and_removes_activity_and_related_event_interest(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $component = Livewire::actingAs($user)->test(BrowseEvents::class);

        $component->call('toggleActivityInterest', $activity->id);
        $this->assertTrue($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
        $this->assertTrue($user->fresh()->interestedEvents()->whereKey($event->id)->exists());

        $component->call('toggleActivityInterest', $activity->id);
        $this->assertFalse($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
    }
}
