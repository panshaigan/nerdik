<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabAutoOpenClosestFutureGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_tab_opens_closest_future_group_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:30:00'));

        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $past = now()->copy()->subHours(2)->setMinute(10)->setSecond(0);
        $futureWithoutActivity = now()->copy()->addHour()->setMinute(0)->setSecond(0);
        $futureWithActivity = now()->copy()->addHours(2)->setMinute(0)->setSecond(0);

        Slot::factory()->create([
            'event_id' => $event->id,
            'starts_at' => $past,
            'ends_at' => $past->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'starts_at' => $futureWithoutActivity,
            'ends_at' => $futureWithoutActivity->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => $futureWithActivity,
            'ends_at' => $futureWithActivity->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $html = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->html();

        $futureWithoutActivityGroupId = 'event-slot-group-'.$futureWithoutActivity->getTimestamp();
        $futureWithActivityGroupId = 'event-slot-group-'.$futureWithActivity->getTimestamp();
        $pastGroupId = 'event-slot-group-'.$past->getTimestamp();

        $futureWithoutActivityPos = strpos($html, 'data-ui="'.$futureWithoutActivityGroupId.'"');
        $this->assertNotFalse($futureWithoutActivityPos);
        $this->assertStringNotContainsString('checked', substr($html, (int) $futureWithoutActivityPos, 600));

        $futureWithActivityPos = strpos($html, 'data-ui="'.$futureWithActivityGroupId.'"');
        $this->assertNotFalse($futureWithActivityPos);
        $this->assertStringContainsString('checked', substr($html, (int) $futureWithActivityPos, 600));

        $pastPos = strpos($html, 'data-ui="'.$pastGroupId.'"');
        $this->assertNotFalse($pastPos);
        $this->assertStringNotContainsString('checked', substr($html, (int) $pastPos, 600));
    }
}
