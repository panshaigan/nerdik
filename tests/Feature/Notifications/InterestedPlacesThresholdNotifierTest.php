<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\ActivityPlacesLowNotification;
use App\Notifications\EventPlacesLowNotification;
use App\Services\EventActivitySignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InterestedPlacesThresholdNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_crossing_notifies_interested_users(): void
    {
        Notification::fake();

        $follower = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'max_participants' => 10,
        ]);
        $follower->interestedActivities()->attach($activity->id);

        $this->seedParticipants($activity, 7);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertSentTo($follower, ActivityPlacesLowNotification::class, function (ActivityPlacesLowNotification $notification) use ($activity): bool {
            return (int) $notification->activity->id === (int) $activity->id
                && $notification->remaining === 2
                && $notification->max === 10;
        });
    }

    public function test_activity_does_not_notify_when_already_below_threshold(): void
    {
        Notification::fake();

        $follower = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'max_participants' => 10,
        ]);
        $follower->interestedActivities()->attach($activity->id);

        $this->seedParticipants($activity, 8);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertNotSentTo($follower, ActivityPlacesLowNotification::class);
    }

    public function test_activity_skips_uncapped_activities(): void
    {
        Notification::fake();

        $follower = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'max_participants' => null,
        ]);
        $follower->interestedActivities()->attach($activity->id);

        $this->seedParticipants($activity, 20);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertNotSentTo($follower, ActivityPlacesLowNotification::class);
    }

    public function test_event_aggregate_crossing_notifies_event_followers(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $eventFollower = User::factory()->create();
        $joiner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activityA = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => 10,
        ]);
        $activityB = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => 10,
        ]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activityA->id]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activityB->id]);

        $eventFollower->interestedEvents()->attach($event->id);

        $this->seedParticipants($activityA, 10);
        $this->seedParticipants($activityB, 4);

        app(EventActivitySignupService::class)->userJoinActivity($activityB, $joiner);

        Notification::assertSentTo($eventFollower, EventPlacesLowNotification::class, function (EventPlacesLowNotification $notification) use ($event): bool {
            return (int) $notification->event->id === (int) $event->id
                && $notification->remaining === 5
                && $notification->max === 20;
        });
        Notification::assertNotSentTo($eventFollower, ActivityPlacesLowNotification::class);
    }

    public function test_event_skips_when_any_programme_activity_is_uncapped(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $eventFollower = User::factory()->create();
        $joiner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $capped = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => 4,
        ]);
        $uncapped = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => null,
        ]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $capped->id]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $uncapped->id]);

        $eventFollower->interestedEvents()->attach($event->id);
        $this->seedParticipants($capped, 2);

        app(EventActivitySignupService::class)->userJoinActivity($capped, $joiner);

        Notification::assertNotSentTo($eventFollower, EventPlacesLowNotification::class);
    }

    public function test_user_following_both_receives_only_activity_notification_when_both_cross(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $joiner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => 4,
        ]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activity->id]);

        $follower->interestedActivities()->attach($activity->id);
        $follower->interestedEvents()->attach($event->id);

        $this->seedParticipants($activity, 2);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertSentTo($follower, ActivityPlacesLowNotification::class);
        Notification::assertNotSentTo($follower, EventPlacesLowNotification::class);
    }

    public function test_event_only_follower_still_notified_when_activity_also_crosses(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $activityFollower = User::factory()->create();
        $eventFollower = User::factory()->create();
        $joiner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'max_participants' => 4,
        ]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activity->id]);

        $activityFollower->interestedActivities()->attach($activity->id);
        $eventFollower->interestedEvents()->attach($event->id);

        $this->seedParticipants($activity, 2);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertSentTo($activityFollower, ActivityPlacesLowNotification::class);
        Notification::assertNotSentTo($activityFollower, EventPlacesLowNotification::class);
        Notification::assertSentTo($eventFollower, EventPlacesLowNotification::class);
        Notification::assertNotSentTo($eventFollower, ActivityPlacesLowNotification::class);
    }

    public function test_joiner_is_not_notified(): void
    {
        Notification::fake();

        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'max_participants' => 4,
        ]);
        $joiner->interestedActivities()->attach($activity->id);

        $this->seedParticipants($activity, 2);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        Notification::assertNotSentTo($joiner, ActivityPlacesLowNotification::class);
    }

    public function test_activity_places_low_respects_opt_out(): void
    {
        $follower = User::factory()->create();
        $follower->profile()->update([
            'notification_preferences' => [
                NotificationPreferenceKey::ActivityPlacesLow->value => [
                    'in_app' => false,
                    'email' => false,
                ],
            ],
        ]);
        $activity = Activity::factory()->create([
            'max_participants' => 4,
        ]);

        $notification = new ActivityPlacesLowNotification($activity, 1, 4);

        $this->assertSame([], $notification->via($follower->fresh()));
    }

    public function test_configurable_threshold_is_respected(): void
    {
        config(['interested_places.remaining_ratio_threshold' => 0.5]);
        Notification::fake();

        $follower = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'max_participants' => 10,
        ]);
        $follower->interestedActivities()->attach($activity->id);

        $this->seedParticipants($activity, 4);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $joiner);

        // 5/10 filled → 50% remaining → crosses 0.5 threshold
        Notification::assertSentTo($follower, ActivityPlacesLowNotification::class);
    }

    private function seedParticipants(Activity $activity, int $count): void
    {
        User::factory()->count($count)->create()->each(function (User $user) use ($activity): void {
            ActivityUser::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $user->id,
            ]);
        });
    }
}
