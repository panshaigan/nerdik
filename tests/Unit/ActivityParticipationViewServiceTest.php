<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityParticipationViewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityParticipationViewServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_has_no_join_and_no_participation_flags(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
        ]);

        $vm = app(ActivityParticipationViewService::class)->forShow($activity, null);

        $this->assertFalse($vm->isParticipant);
        $this->assertFalse($vm->onWaitlist);
        $this->assertFalse($vm->canJoin);
        $this->assertFalse($vm->hasInterest);
        $this->assertFalse($vm->canManageActivity);
    }

    #[Test]
    public function cancelled_activity_sets_state_blocked_and_disallows_join(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'cancelled_at' => now(),
        ]);

        $vm = app(ActivityParticipationViewService::class)->forShow($activity, $user);

        $this->assertSame(__('ui.activities.signup_blocked_cancelled'), $vm->stateBlockedMessage);
        $this->assertFalse($vm->canJoin);
    }

    #[Test]
    public function active_enrollment_window_exposes_per_activity_remaining(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $event = Event::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'max_allowed_participants_per_activity' => 5,
            'max_activities_per_user' => 0,
        ]);

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);
        $event->load('enrollmentWindows');

        $vm = app(ActivityParticipationViewService::class)->forShow($activity->fresh(['slot.event.enrollmentWindows']), null);

        $this->assertSame(5, $vm->activeWindowPerActivityMax);
        $this->assertSame(5, $vm->activeWindowRemainingForActivity);

        Carbon::setTestNow();
    }
}
