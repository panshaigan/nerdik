<?php

namespace Tests\Unit\Services;

use App\Enums\LotteryDrawTrigger;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityLotteryDraw;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\SlotParticipationConstraintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlotParticipationConstraintServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlotParticipationConstraintService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SlotParticipationConstraintService::class);
    }

    #[Test]
    public function constraining_slots_keeps_only_forcing_slots(): void
    {
        $event = Event::factory()->create();
        $forcing = Slot::factory()->forcesParticipation(ParticipationMode::Open)->create(['event_id' => $event->id]);
        $plain = Slot::factory()->create(['event_id' => $event->id, 'forces_participation_settings' => false]);

        $result = $this->service->constrainingSlots(collect([$forcing, $plain]));

        $this->assertCount(1, $result);
        $this->assertSame($forcing->id, $result->first()->id);
    }

    #[Test]
    public function allowed_participation_modes_returns_unique_forced_modes(): void
    {
        $event = Event::factory()->create();
        $lottery = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 24)->create(['event_id' => $event->id]);
        $approval = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create(['event_id' => $event->id]);
        $duplicate = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create(['event_id' => $event->id]);

        $modes = $this->service->allowedParticipationModes(collect([$lottery, $approval, $duplicate]));

        $this->assertEqualsCanonicalizing(
            [ParticipationMode::Lottery->value, ParticipationMode::HostApproval->value],
            $modes,
        );
    }

    #[Test]
    public function allowed_lottery_draw_hours_is_empty_for_non_lottery_mode(): void
    {
        $event = Event::factory()->create();
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Open)->create(['event_id' => $event->id]);

        $hours = $this->service->allowedLotteryDrawHoursForMode(collect([$slot]), ParticipationMode::Open->value);

        $this->assertSame([], $hours);
    }

    #[Test]
    public function allowed_lottery_draw_hours_lists_unique_values_for_lottery_slots(): void
    {
        $event = Event::factory()->create();
        $twelve = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 12)->create(['event_id' => $event->id]);
        $twentyFour = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 24)->create(['event_id' => $event->id]);

        $hours = $this->service->allowedLotteryDrawHoursForMode(
            collect([$twelve, $twentyFour, $twelve]),
            ParticipationMode::Lottery->value,
        );

        $this->assertEqualsCanonicalizing([12, 24], $hours);
    }

    #[Test]
    public function allowed_allows_observers_for_mode_filters_by_participation_mode(): void
    {
        $event = Event::factory()->create();
        $observersAllowed = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 24, true)->create(['event_id' => $event->id]);
        $observersDenied = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 12, false)->create(['event_id' => $event->id]);
        $openSlot = Slot::factory()->forcesParticipation(ParticipationMode::Open)->create(['event_id' => $event->id]);

        $observers = $this->service->allowedAllowsObserversForMode(
            collect([$observersAllowed, $observersDenied, $openSlot]),
            ParticipationMode::Lottery->value,
        );

        $this->assertEqualsCanonicalizing([true, false], $observers);
    }

    #[Test]
    public function activity_matches_slot_when_slot_does_not_force_settings(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::HostApproval,
        ]);
        $slot = Slot::factory()->create(['forces_participation_settings' => false]);

        $this->assertTrue($this->service->activityMatchesSlot($activity, $slot));
    }

    #[Test]
    public function activity_matches_slot_false_on_participation_mode_mismatch(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Open,
            'allows_observers' => false,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create();

        $this->assertFalse($this->service->activityMatchesSlot($activity, $slot));
    }

    #[Test]
    public function activity_matches_slot_false_on_lottery_hours_mismatch(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 12,
            'allows_observers' => false,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 24)->create();

        $this->assertFalse($this->service->activityMatchesSlot($activity, $slot));
    }

    #[Test]
    public function activity_matches_slot_false_on_observers_mismatch(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::HostApproval,
            'allows_observers' => true,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval, null, false)->create();

        $this->assertFalse($this->service->activityMatchesSlot($activity, $slot));
    }

    #[Test]
    public function activity_matches_slot_true_when_all_forced_fields_align(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 36,
            'allows_observers' => true,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 36, true)->create();

        $this->assertTrue($this->service->activityMatchesSlot($activity, $slot));
    }

    #[Test]
    public function apply_forced_settings_updates_activity_participation_fields(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Open,
            'lottery_draw_in_hours' => null,
            'allows_observers' => false,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 18, true)->create();

        $this->service->applyForcedSettingsToActivity($slot, $activity);
        $activity->refresh();

        $this->assertSame(ParticipationMode::Lottery, $activity->participation_mode);
        $this->assertSame(18, (int) $activity->lottery_draw_in_hours);
        $this->assertTrue($activity->allows_observers);
    }

    #[Test]
    public function apply_forced_settings_clears_lottery_resolved_at_and_draws_when_mode_changes(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 24,
            'lottery_resolved_at' => now(),
            'allows_observers' => false,
        ]);
        ActivityLotteryDraw::query()->create([
            'activity_id' => $activity->id,
            'drawn_at' => now()->addDay(),
            'trigger' => LotteryDrawTrigger::Final,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create();

        $this->service->applyForcedSettingsToActivity($slot, $activity);
        $activity->refresh();

        $this->assertNull($activity->lottery_resolved_at);
        $this->assertNull($activity->lottery_draw_in_hours);
        $this->assertSame(ParticipationMode::HostApproval, $activity->participation_mode);
        $this->assertSame(0, ActivityLotteryDraw::query()->where('activity_id', $activity->id)->count());
    }

    #[Test]
    public function apply_forced_settings_clears_lottery_state_when_draw_hours_change(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 12,
            'lottery_resolved_at' => now(),
            'allows_observers' => false,
        ]);
        ActivityLotteryDraw::query()->create([
            'activity_id' => $activity->id,
            'drawn_at' => now()->addDay(),
            'trigger' => LotteryDrawTrigger::Final,
        ]);
        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 48)->create();

        $this->service->applyForcedSettingsToActivity($slot, $activity);
        $activity->refresh();

        $this->assertNull($activity->lottery_resolved_at);
        $this->assertSame(48, (int) $activity->lottery_draw_in_hours);
        $this->assertSame(0, ActivityLotteryDraw::query()->where('activity_id', $activity->id)->count());
    }

    #[Test]
    public function apply_forced_settings_no_op_when_slot_does_not_force(): void
    {
        $activity = Activity::factory()->create([
            'participation_mode' => ParticipationMode::Open,
            'allows_observers' => true,
        ]);
        $slot = Slot::factory()->create(['forces_participation_settings' => false]);

        $this->service->applyForcedSettingsToActivity($slot, $activity);
        $activity->refresh();

        $this->assertSame(ParticipationMode::Open, $activity->participation_mode);
        $this->assertTrue($activity->allows_observers);
    }
}
