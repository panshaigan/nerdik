<?php

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\EventSeries;
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

    public function test_dashboard_feed_activity_line_mentions_late_announce(): void
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
            'name' => 'Late Tip Activity',
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
                $lines = collect($payload['items'] ?? [])
                    ->firstWhere('category', 'dashboard_feed')['lines'] ?? [];
                $joined = implode("\n", is_array($lines) ? $lines : []);

                return str_contains($joined, 'Late Tip Activity')
                    && str_contains($joined, 'clock button');
            }
        );
    }

    public function test_dashboard_feed_when_uses_start_time_not_only_end(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledDashboardFeedPreferences(),
        ]);

        Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Weekend Con',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(22, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($user): bool {
                $payload = $notification->toArray($user);
                $lines = collect($payload['items'] ?? [])
                    ->firstWhere('category', 'dashboard_feed')['lines'] ?? [];

                $joined = implode("\n", is_array($lines) ? $lines : []);

                return str_contains($joined, 'Weekend Con')
                    && str_contains($joined, '2026-06-02 10:00')
                    && str_contains($joined, '22:00');
            }
        );
    }

    public function test_dashboard_feed_includes_event_that_starts_tomorrow_and_ends_later(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledDashboardFeedPreferences(),
        ]);

        Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Long Con',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(18, 0),
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($user): bool {
                $payload = $notification->toArray($user);
                $lines = collect($payload['items'] ?? [])
                    ->firstWhere('category', 'dashboard_feed')['lines'] ?? [];

                $joined = implode("\n", is_array($lines) ? $lines : []);

                return str_contains($joined, 'Long Con')
                    && str_contains($joined, '2026-06-02 10:00');
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

    public function test_organizer_low_participation_within_event_runway_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.organizer_low_participation_runway_days', 7);
        $this->travelTo('2026-06-01 09:00:00');

        $organizer = User::factory()->create();
        $organizer->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledOrganizerLowParticipationPreferences(),
        ]);
        $host = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Underfilled Slot Activity',
            'min_participants' => 4,
            'cancellation_deadline_in_hours' => null,
        ]);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'name' => 'Runway Event',
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'ends_at' => now()->addDays(5)->setTime(22, 0),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDays(5)->setTime(14, 0),
            'ends_at' => now()->addDays(5)->setTime(16, 0),
            'created_by' => $organizer->id,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $organizer,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($organizer): bool {
                $payload = $notification->toArray($organizer);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'organizer_low_participation');
            }
        );
        Notification::assertNotSentTo($host, ScheduledPeriodicDigestNotification::class);
    }

    public function test_organizer_low_participation_outside_event_runway_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.organizer_low_participation_runway_days', 7);
        $this->travelTo('2026-06-01 09:00:00');

        $organizer = User::factory()->create();
        $organizer->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledOrganizerLowParticipationPreferences(),
        ]);
        $host = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 4,
        ]);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(10)->setTime(10, 0),
            'ends_at' => now()->addDays(10)->setTime(22, 0),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDays(10)->setTime(14, 0),
            'ends_at' => now()->addDays(10)->setTime(16, 0),
            'created_by' => $organizer->id,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_low_participation_does_not_use_activity_start_runway(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.host_low_participation_deadline_offsets', [3, 1]);
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostLowParticipationPreferences(),
        ]);

        // Activity starts in 5 days but cancel deadline is in 4 days — not a 3-/1-day host offset.
        Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 4,
            'starts_at' => now()->addDays(5)->setTime(18, 0),
            'ends_at' => now()->addDays(5)->setTime(20, 0),
            'cancellation_deadline_in_hours' => 24,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_low_participation_three_days_before_deadline_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.host_low_participation_deadline_offsets', [3, 1]);
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostLowParticipationPreferences(),
        ]);

        // Deadline = start - 408h = now + 20d - 17d = now + 3d.
        Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 4,
            'starts_at' => now()->addDays(20)->setTime(18, 0),
            'ends_at' => now()->addDays(20)->setTime(20, 0),
            'cancellation_deadline_in_hours' => 17 * 24,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_low_participation');
            }
        );
    }

    public function test_host_low_participation_day_before_deadline_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.host_low_participation_deadline_offsets', [3, 1]);
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostLowParticipationPreferences(),
        ]);

        // Deadline = start - 336h = now + 15d - 14d = now + 1d.
        Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 4,
            'starts_at' => now()->addDays(15)->setTime(18, 0),
            'ends_at' => now()->addDays(15)->setTime(20, 0),
            'cancellation_deadline_in_hours' => 14 * 24,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host): bool {
                $payload = $notification->toArray($host);

                return collect($payload['items'] ?? [])
                    ->contains(fn (array $item): bool => ($item['category'] ?? '') === 'host_low_participation');
            }
        );
    }

    public function test_host_low_participation_two_days_before_deadline_is_not_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.host_low_participation_deadline_offsets', [3, 1]);
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostLowParticipationPreferences(),
        ]);

        // Deadline = start - 240h = now + 12d - 10d = now + 2d (not 3 or 1).
        Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 4,
            'starts_at' => now()->addDays(12)->setTime(18, 0),
            'ends_at' => now()->addDays(12)->setTime(20, 0),
            'cancellation_deadline_in_hours' => 10 * 24,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_low_participation_skipped_when_at_minimum(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        config()->set('scheduled_notifications.host_low_participation_deadline_offsets', [3, 1]);
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledHostLowParticipationPreferences(),
        ]);
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 1,
            'starts_at' => now()->addDays(20)->setTime(18, 0),
            'ends_at' => now()->addDays(20)->setTime(20, 0),
            'cancellation_deadline_in_hours' => 17 * 24,
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

    public function test_host_propose_next_edition_day_after_event_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent, $nextEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $host,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($host, $nextEvent): bool {
                $payload = $notification->toArray($host);
                $item = collect($payload['items'] ?? [])
                    ->first(fn (array $row): bool => ($row['category'] ?? '') === 'host_propose_next_edition');

                return $item !== null
                    && ($item['url'] ?? '') === route('events.propose', ['event' => $nextEvent], false);
            }
        );
    }

    public function test_participant_follow_next_edition_day_after_event_is_included(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $participant = User::factory()->create();
        $participant->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $host = User::factory()->create();
        $organizer = User::factory()->create();

        [$pastEvent, $nextEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer, $participant);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $participant,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($participant, $nextEvent): bool {
                $payload = $notification->toArray($participant);
                $item = collect($payload['items'] ?? [])
                    ->first(fn (array $row): bool => ($row['category'] ?? '') === 'participant_follow_next_edition');

                return $item !== null
                    && ($item['url'] ?? '') === route('events.show', ['event' => $nextEvent], false);
            }
        );
    }

    public function test_series_next_edition_skipped_when_no_next_event(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $organizer->id]);
        $pastEvent = Event::factory()->create([
            'created_by' => $organizer->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(18, 0),
        ]);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_series_next_edition_skipped_when_next_event_cancelled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent] = $this->createSeriesPastAndNextEvents($organizer, nextCancelled: true);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_series_next_edition_skipped_when_past_event_cancelled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent] = $this->createSeriesPastAndNextEvents($organizer, pastCancelled: true);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_series_next_edition_skipped_on_same_day_event_end(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $organizer->id]);
        $pastEvent = Event::factory()->create([
            'created_by' => $organizer->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);
        Event::factory()->create([
            'created_by' => $organizer->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addMonth()->setTime(10, 0),
            'ends_at' => now()->addMonth()->setTime(18, 0),
        ]);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_host_propose_next_edition_skipped_when_already_proposed(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent, $nextEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);
        ActivityProposal::factory()->create([
            'event_id' => $nextEvent->id,
            'created_by' => $host->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_participant_follow_next_edition_skipped_when_already_following(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $participant = User::factory()->create();
        $participant->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $host = User::factory()->create();
        $organizer = User::factory()->create();

        [$pastEvent, $nextEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer, $participant);
        $participant->interestedEvents()->attach($nextEvent->id);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_series_next_edition_skipped_when_preference_disabled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledParticipantDeadlinePreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $this->attachHostedActivityOnEvent($host, $pastEvent, $organizer);

        Notification::fake();
        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_series_next_edition_skipped_when_activity_cancelled(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $host = User::factory()->create();
        $host->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $participant = User::factory()->create();
        $participant->profile()->update([
            'timezone' => 'UTC',
            'notification_preferences' => $this->onlyScheduledSeriesNextEditionPreferences(),
        ]);
        $organizer = User::factory()->create();

        [$pastEvent] = $this->createSeriesPastAndNextEvents($organizer);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(12, 0),
            'ends_at' => now()->subDay()->setTime(14, 0),
            'cancelled_at' => now()->subDays(2),
        ]);
        Slot::factory()->create([
            'event_id' => $pastEvent->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->subDay()->setTime(12, 0),
            'ends_at' => now()->subDay()->setTime(14, 0),
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

        Notification::assertNothingSent();
    }

    /**
     * @return array{0: Event, 1: Event}
     */
    private function createSeriesPastAndNextEvents(
        User $organizer,
        bool $pastCancelled = false,
        bool $nextCancelled = false,
    ): array {
        $series = EventSeries::factory()->create(['created_by' => $organizer->id]);
        $pastEvent = Event::factory()->create([
            'created_by' => $organizer->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(18, 0),
            'cancelled_at' => $pastCancelled ? now()->subDays(2) : null,
        ]);
        $nextEvent = Event::factory()->create([
            'created_by' => $organizer->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addMonth()->setTime(10, 0),
            'ends_at' => now()->addMonth()->setTime(18, 0),
            'cancelled_at' => $nextCancelled ? now() : null,
        ]);

        return [$pastEvent, $nextEvent];
    }

    private function attachHostedActivityOnEvent(
        User $host,
        Event $event,
        User $organizer,
        ?User $participant = null,
    ): Activity {
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'starts_at' => now()->subDay()->setTime(12, 0),
            'ends_at' => now()->subDay()->setTime(14, 0),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->subDay()->setTime(12, 0),
            'ends_at' => now()->subDay()->setTime(14, 0),
            'created_by' => $organizer->id,
        ]);

        if ($participant !== null) {
            DB::table('activity_user')->insert([
                'activity_id' => $activity->id,
                'user_id' => $participant->id,
                'is_absent' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $activity;
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
    private function onlyScheduledSeriesNextEditionPreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledSeriesNextEdition;
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
    private function onlyScheduledOrganizerLowParticipationPreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledOrganizerLowParticipation;
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
    private function onlyScheduledHostLowParticipationPreferences(): array
    {
        $preferences = [];
        foreach (NotificationPreferenceKey::cases() as $key) {
            $enabled = $key === NotificationPreferenceKey::ScheduledHostLowParticipation;
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
