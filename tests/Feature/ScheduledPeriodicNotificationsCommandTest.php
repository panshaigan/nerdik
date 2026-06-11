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

        $user = User::factory()->create();
        $user->profile()->update([
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
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addDays(2),
        ]);
        $user->interestedEvents()->attach($event->id);

        $activity = Activity::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addHours(30),
            'ends_at' => now()->addHours(32),
            'cancellation_deadline_in_hours' => 6,
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

    public function test_enrollment_window_outside_24h_lookahead_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update(['timezone' => 'UTC']);
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addHours(30),
            'ends_at' => now()->addDays(2),
        ]);
        $user->interestedEvents()->attach($event->id);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_organizer_pending_proposals_are_not_collected_in_digest(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-02 09:00:00');

        $organizer = User::factory()->create();
        $organizer->profile()->update(['timezone' => 'UTC']);
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
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addDays(1),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_cancellation_deadline_within_24h_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update(['timezone' => 'UTC']);
        $host = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(22),
            'cancellation_deadline_in_hours' => 8,
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

        Notification::assertSentTo(
            $user,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($user): bool {
                $payload = $notification->toArray($user);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'participant_cancellation_deadline');
            }
        );
    }
}
