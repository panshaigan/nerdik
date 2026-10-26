<?php

namespace Tests\Feature;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
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

    public function test_command_skips_send_when_scheduled_preference_disabled(): void
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

    public function test_command_sends_digest_when_collector_would_return_items(): void
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
