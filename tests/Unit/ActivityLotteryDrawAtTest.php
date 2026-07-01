<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLotteryDrawAtTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function self_hosted_activity_draw_time_is_start_minus_configured_hours(): void
    {
        $startsAt = Carbon::parse('2026-07-10 18:00:00', 'UTC');
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $startsAt,
            'lottery_draw_in_hours' => 24,
        ]);

        $drawAt = $activity->lotteryDrawAt();

        $this->assertNotNull($drawAt);
        $this->assertTrue($drawAt->equalTo($startsAt->copy()->subHours(24)));
    }

    #[Test]
    public function scheduled_activity_uses_slot_start_over_activity_start(): void
    {
        $slotStart = Carbon::parse('2026-07-10 14:00:00', 'UTC');
        $activityStart = Carbon::parse('2026-07-10 10:00:00', 'UTC');
        $event = Event::factory()->create();

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'starts_at' => $activityStart,
            'lottery_draw_in_hours' => 12,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => $slotStart,
        ]);

        $drawAt = $activity->fresh('slot')->lotteryDrawAt();

        $this->assertNotNull($drawAt);
        $this->assertTrue($drawAt->equalTo($slotStart->copy()->subHours(12)));
    }

    #[Test]
    public function returns_null_when_hours_unset(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'lottery_draw_in_hours' => null,
        ]);

        $this->assertNull($activity->lotteryDrawAt());
    }

    #[Test]
    public function returns_null_when_start_time_missing(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => null,
            'lottery_draw_in_hours' => 24,
        ]);

        $this->assertNull($activity->lotteryDrawAt());
    }
}
