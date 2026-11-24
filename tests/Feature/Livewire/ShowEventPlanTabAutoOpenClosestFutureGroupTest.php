<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowPlanTab;
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

    public function test_plan_tab_opens_all_slot_groups_on_closest_day(): void
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

        $html = Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->html();

        $futureWithoutActivityGroupId = 'event-slot-group-'.$futureWithoutActivity->getTimestamp();
        $futureWithActivityGroupId = 'event-slot-group-'.$futureWithActivity->getTimestamp();
        $pastGroupId = 'event-slot-group-'.$past->getTimestamp();

        foreach ([$pastGroupId, $futureWithoutActivityGroupId, $futureWithActivityGroupId] as $groupId) {
            $pos = strpos($html, 'data-ui="'.$groupId.'"');
            $this->assertNotFalse($pos);
            $this->assertStringContainsString('checked', substr($html, (int) $pos, 600));
        }
    }

    public function test_plan_tab_keeps_groups_for_other_days_collapsed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:30:00'));

        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
        ]);

        $today = now()->copy()->addHours(2)->setMinute(0)->setSecond(0);
        $tomorrow = now()->copy()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
        $todayActivity = Activity::factory()->scheduled()->create(['created_by' => $user->id]);
        $tomorrowActivity = Activity::factory()->scheduled()->create(['created_by' => $user->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $todayActivity->id,
            'starts_at' => $today,
            'ends_at' => $today->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $tomorrowActivity->id,
            'starts_at' => $tomorrow,
            'ends_at' => $tomorrow->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $html = Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->html();

        $todayGroupId = 'event-slot-group-'.$today->getTimestamp();
        $todayPos = strpos($html, 'data-ui="'.$todayGroupId.'"');
        $this->assertNotFalse($todayPos);
        $this->assertStringContainsString('checked', substr($html, (int) $todayPos, 600));

        $tomorrowGroupId = 'event-slot-group-'.$tomorrow->getTimestamp();
        $tomorrowPos = strpos($html, 'data-ui="'.$tomorrowGroupId.'"');
        $this->assertNotFalse($tomorrowPos);
        $this->assertStringNotContainsString('checked', substr($html, (int) $tomorrowPos, 600));
    }

    public function test_plan_tab_opens_closest_day_when_it_is_not_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 22:00:00'));

        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        $earlierToday = now()->copy()->setHour(8)->setMinute(0)->setSecond(0);
        $tomorrowMorning = now()->copy()->addDay()->setHour(9)->setMinute(0)->setSecond(0);
        $todayActivity = Activity::factory()->scheduled()->create(['created_by' => $user->id]);
        $tomorrowActivity = Activity::factory()->scheduled()->create(['created_by' => $user->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $todayActivity->id,
            'starts_at' => $earlierToday,
            'ends_at' => $earlierToday->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $tomorrowActivity->id,
            'starts_at' => $tomorrowMorning,
            'ends_at' => $tomorrowMorning->copy()->addHour(),
            'created_by' => $user->id,
        ]);

        $html = Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->html();

        $todayGroupId = 'event-slot-group-'.$earlierToday->getTimestamp();
        $todayPos = strpos($html, 'data-ui="'.$todayGroupId.'"');
        $this->assertNotFalse($todayPos);
        $this->assertStringNotContainsString('checked', substr($html, (int) $todayPos, 600));

        $tomorrowGroupId = 'event-slot-group-'.$tomorrowMorning->getTimestamp();
        $tomorrowPos = strpos($html, 'data-ui="'.$tomorrowGroupId.'"');
        $this->assertNotFalse($tomorrowPos);
        $this->assertStringContainsString('checked', substr($html, (int) $tomorrowPos, 600));
    }
}
