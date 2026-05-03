<?php

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledPeriodicNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_single_digest_and_is_idempotent_for_same_day(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create([
            'timezone' => 'UTC',
        ]);
        $organizer = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $user->interestedEvents()->attach($event->id);

        $activity = Activity::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'cancellation_deadline_in_hours' => 24,
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentToTimes($user, ScheduledPeriodicDigestNotification::class, 1);
        $firstDispatchCount = DB::table('scheduled_notification_dispatches')->count();
        $this->assertGreaterThan(0, $firstDispatchCount);

        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentToTimes($user, ScheduledPeriodicDigestNotification::class, 1);
        $this->assertSame($firstDispatchCount, DB::table('scheduled_notification_dispatches')->count());
    }

    public function test_organizer_pending_proposal_is_not_sent_outside_baseline_or_escalation_window(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-02 09:00:00'); // Tuesday

        $organizer = User::factory()->create([
            'timezone' => 'UTC',
        ]);
        $proposer = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $proposer->id,
        ]);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
        ]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'status' => ActivityProposalStatus::Pending,
            'created_by' => $proposer->id,
        ]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(11),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
