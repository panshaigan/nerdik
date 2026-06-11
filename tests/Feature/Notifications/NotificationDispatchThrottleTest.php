<?php

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Services\EventActivitySignupService;
use App\Services\Notifications\NotificationDispatchThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDispatchThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_throttle_suppresses_repeat_notification_within_cooldown(): void
    {
        config([
            'notification_throttle.enabled' => true,
            'notification_throttle.cooldown_seconds' => [
                ActivityParticipantJoinedNotification::class => 900,
            ],
        ]);

        $host = User::factory()->create();
        $host->profile()->update([
            'notification_preferences' => [
                'activity_participant_joined' => [
                    'in_app' => true,
                    'email' => true,
                    'every_join' => true,
                ],
            ],
        ]);
        $firstJoiner = User::factory()->create();
        $secondJoiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'max_participants' => 10,
        ]);

        $throttle = app(NotificationDispatchThrottle::class);
        $notification = new ActivityParticipantJoinedNotification($activity, $firstJoiner, 1);

        $this->assertFalse($throttle->shouldSuppress($notification, $host));

        $throttle->record($notification, $host);

        $this->assertTrue(
            $throttle->shouldSuppress(
                new ActivityParticipantJoinedNotification($activity, $secondJoiner, 2),
                $host
            )
        );
        $this->assertSame([], (new ActivityParticipantJoinedNotification($activity, $secondJoiner, 2))->via($host->fresh()));
    }

    public function test_dispatch_throttle_allows_notification_after_cooldown_expires(): void
    {
        config([
            'notification_throttle.enabled' => true,
            'notification_throttle.cooldown_seconds' => [
                ActivityParticipantJoinedNotification::class => 60,
            ],
        ]);

        $host = User::factory()->create();
        $host->profile()->update([
            'notification_preferences' => [
                'activity_participant_joined' => [
                    'in_app' => true,
                    'email' => true,
                    'every_join' => true,
                ],
            ],
        ]);
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $throttle = app(NotificationDispatchThrottle::class);
        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 1);

        $throttle->record($notification, $host->fresh());
        $this->assertTrue($throttle->shouldSuppress($notification, $host));

        $this->travel(61)->seconds();

        $this->assertFalse($throttle->shouldSuppress($notification, $host));
    }

    public function test_join_leave_cycle_suppresses_repeat_host_notifications_within_cooldown(): void
    {
        config([
            'notification_throttle.enabled' => true,
            'notification_throttle.cooldown_seconds' => [
                ActivityParticipantJoinedNotification::class => 900,
                ActivityParticipantLeftNotification::class => 900,
            ],
            'notification_throttle.participation_mutations_per_minute' => 10,
        ]);

        $host = User::factory()->create();
        $host->profile()->update([
            'notification_preferences' => [
                'activity_participant_joined' => [
                    'in_app' => true,
                    'email' => true,
                    'every_join' => true,
                ],
            ],
        ]);
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'max_participants' => 10,
        ]);

        $signup = app(EventActivitySignupService::class);

        $signup->userJoinActivity($activity->fresh(), $joiner);
        $this->assertSame(
            1,
            $host->fresh()->notifications()
                ->where('type', ActivityParticipantJoinedNotification::class)
                ->count()
        );

        $participant = $activity->participants()->where('user_id', $joiner->id)->firstOrFail();
        $signup->userLeaveActivity($activity->fresh(), $participant);
        $this->assertSame(
            1,
            $host->fresh()->notifications()
                ->where('type', ActivityParticipantLeftNotification::class)
                ->count()
        );

        $signup->userJoinActivity($activity->fresh(), $joiner);
        $this->assertSame(
            1,
            $host->fresh()->notifications()
                ->where('type', ActivityParticipantJoinedNotification::class)
                ->count()
        );
    }
}
