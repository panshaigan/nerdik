<?php

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
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

    public function test_cancellation_deadline_tomorrow_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledParticipantDeadlinePreferences(),
        ]);
        $host = User::factory()->create();

        // Deadline = start - 8h = now + 32h = 2026-06-02 17:00 (tomorrow).
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addHours(40),
            'ends_at' => now()->addHours(42),
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

    public function test_cancellation_deadline_same_day_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledParticipantDeadlinePreferences(),
        ]);
        $host = User::factory()->create();

        // Deadline = start - 8h = now + 12h = 2026-06-01 21:00 (today, within old 24h window).
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

        Notification::assertNothingSent();
    }

    public function test_cancellation_deadline_beyond_tomorrow_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledParticipantDeadlinePreferences(),
        ]);
        $host = User::factory()->create();

        // Deadline = start - 8h = now + 56h = 2026-06-03 17:00 (day after tomorrow).
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addHours(64),
            'ends_at' => now()->addHours(66),
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

        Notification::assertNothingSent();
    }

    public function test_host_mark_absences_day_after_end_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(14, 0),
            'ends_at' => now()->subDay()->setTime(16, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_mark_absences');
            }
        );
    }

    public function test_host_mark_absences_uses_slot_end_when_present(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();
        $organizer = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
        ]);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDay(),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->subDay()->setTime(14, 0),
            'ends_at' => now()->subDay()->setTime(16, 0),
            'created_by' => $organizer->id,
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_mark_absences');
            }
        );
    }

    public function test_host_mark_absences_same_day_end_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->setTime(7, 0),
            'ends_at' => now()->setTime(8, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_mark_absences_two_days_ago_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDays(2)->setTime(14, 0),
            'ends_at' => now()->subDays(2)->setTime(16, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_mark_absences_skipped_when_cancelled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(14, 0),
            'ends_at' => now()->subDay()->setTime(16, 0),
            'cancelled_at' => now()->subDays(2),
            'cancelled_by' => $host->id,
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_mark_absences_skipped_when_all_participants_marked_absent(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(14, 0),
            'ends_at' => now()->subDay()->setTime(16, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_mark_absences_skipped_when_preference_disabled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledParticipantDeadlinePreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(14, 0),
            'ends_at' => now()->subDay()->setTime(16, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_dashboard_feed_tomorrow_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledDashboardFeedPreferences(),
        ]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($user): bool {
                $payload = $notification->toArray($user);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'dashboard_feed');
            }
        );
    }

    public function test_dashboard_feed_same_day_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledDashboardFeedPreferences(),
        ]);

        // Within old 24h window, but still today — should not appear with calendar-tomorrow semantics.
        Activity::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(20, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_dashboard_feed_beyond_tomorrow_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledDashboardFeedPreferences(),
        ]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDays(2)->setTime(14, 0),
            'ends_at' => now()->addDays(2)->setTime(16, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_upcoming_mark_absences_tomorrow_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_upcoming_mark_absences');
            }
        );
    }

    public function test_host_upcoming_mark_absences_uses_slot_start_when_present(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();
        $organizer = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
        ]);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
            'created_by' => $organizer->id,
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_upcoming_mark_absences');
            }
        );
    }

    public function test_host_upcoming_mark_absences_same_day_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(20, 0),
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_upcoming_mark_absences_skipped_when_no_active_participants(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);

        Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_upcoming_mark_absences_skipped_when_cancelled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostMarkAbsencesPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(16, 0),
            'cancelled_at' => now()->subHour(),
            'cancelled_by' => $host->id,
        ]);
        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    private function onlyScheduledParticipantDeadlinePreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledParticipantCancellationDeadline;
            $preferences[$key->value] = [
                'in_app' => $enabled,
                'email' => $enabled,
            ];
        }

        return $preferences;
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    private function onlyScheduledHostMarkAbsencesPreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledHostMarkAbsences;
            $preferences[$key->value] = [
                'in_app' => $enabled,
                'email' => $enabled,
            ];
        }

        return $preferences;
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    private function onlyScheduledDashboardFeedPreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledDashboardFeed;
            $preferences[$key->value] = [
                'in_app' => $enabled,
                'email' => $enabled,
            ];
        }

        return $preferences;
    }
}
