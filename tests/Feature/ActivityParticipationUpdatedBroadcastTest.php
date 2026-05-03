<?php

namespace Tests\Feature;

use App\Events\ActivityParticipationUpdated;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use App\Services\ActivityParticipantRosterService;
use App\Services\ActivityParticipationBroadcaster;
use App\Services\EventActivitySignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ActivityParticipationUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcaster_dispatches_activity_participation_updated(): void
    {
        Event::fake([ActivityParticipationUpdated::class]);

        ActivityParticipationBroadcaster::rosterChanged(42);

        Event::assertDispatched(ActivityParticipationUpdated::class, function (ActivityParticipationUpdated $e): bool {
            return $e->activityId === 42;
        });
    }

    public function test_user_join_activity_dispatches_broadcast_event(): void
    {
        Event::fake([ActivityParticipationUpdated::class]);

        $participant = User::factory()->create();
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => false,
        ]);

        app(EventActivitySignupService::class)->userJoinActivity($activity, $participant);

        Event::assertDispatched(ActivityParticipationUpdated::class, function (ActivityParticipationUpdated $e) use ($activity): bool {
            return $e->activityId === $activity->id;
        });
    }

    public function test_mark_absent_dispatches_broadcast_event(): void
    {
        Event::fake([ActivityParticipationUpdated::class]);

        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => false,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
            'is_absent' => false,
        ]);

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        app(ActivityParticipantRosterService::class)->markParticipantAbsent($participant);

        Event::assertDispatched(ActivityParticipationUpdated::class, function (ActivityParticipationUpdated $e) use ($activity): bool {
            return $e->activityId === $activity->id;
        });
    }
}
