<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationPreferenceKey;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\ActivityParticipantJoinedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityParticipantJoinedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_is_empty_for_routine_join_when_every_join_disabled(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 5,
            'max_participants' => 10,
        ]);

        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 1);

        $this->assertSame([], $notification->via($host));
    }

    public function test_via_includes_channels_for_min_reached_milestone_when_every_join_disabled(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 2,
            'max_participants' => 10,
        ]);

        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 2);
        $channels = $notification->via($host);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_via_respects_email_toggle_for_milestone(): void
    {
        $host = User::factory()->create();
        $host->profile()->update([
            'notification_preferences' => [
                NotificationPreferenceKey::ActivityParticipantJoined->value => [
                    'in_app' => true,
                    'email' => true,
                    'every_join' => false,
                ],
            ],
        ]);
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 1,
            'max_participants' => 5,
        ]);

        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 1);
        $channels = $notification->via($host->fresh());

        $this->assertContains('mail', $channels);
    }

    public function test_via_includes_all_channels_when_every_join_enabled(): void
    {
        $host = User::factory()->create();
        $host->profile()->update([
            'notification_preferences' => [
                NotificationPreferenceKey::ActivityParticipantJoined->value => [
                    'in_app' => true,
                    'email' => true,
                    'every_join' => true,
                ],
            ],
        ]);
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'max_participants' => 10,
        ]);

        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 1);
        $channels = $notification->via($host->fresh());

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_subject_uses_min_reached_copy_when_threshold_crossed(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Board Games',
            'min_participants' => 2,
            'max_participants' => 10,
        ]);

        $notification = new ActivityParticipantJoinedNotification($activity, $joiner, 2);
        $payload = $notification->toArray($host);

        $this->assertTrue($payload['min_reached']);
        $this->assertStringContainsString('Board Games', $payload['toast_title']);
        $this->assertStringContainsString('minimum', strtolower($payload['toast_title']));
    }
}
