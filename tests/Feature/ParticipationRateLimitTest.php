<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Place;
use App\Models\User;
use App\Services\ActivityParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_fourth_participation_mutation_on_same_activity_within_one_minute_is_rejected(): void
    {
        config(['notification_throttle.participation_mutations_per_minute' => 3]);

        $user = User::factory()->create();
        $venue = Place::query()->create([
            'name' => 'Test Venue',
            'type' => 'venue',
            'is_online' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => now()->addDay(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'requires_approval' => false,
            'max_participants' => 10,
        ]);

        $service = app(ActivityParticipationService::class);

        $service->join($activity, $user);
        $service->leave($activity->fresh(), $user);
        $service->join($activity->fresh(), $user);

        $response = $service->leave($activity->fresh(), $user);

        $this->assertTrue($response->getSession()->has('status'));
        $this->assertSame(
            __('ui.activities.participation_rate_limited'),
            $response->getSession()->get('status')
        );
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $user->id)
                ->exists()
        );
    }

    public function test_participation_mutations_on_different_activities_do_not_share_rate_limit(): void
    {
        config(['notification_throttle.participation_mutations_per_minute' => 3]);

        $user = User::factory()->create();
        $venue = Place::query()->create([
            'name' => 'Test Venue',
            'type' => 'venue',
            'is_online' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $activityA = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => now()->addDay(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'requires_approval' => false,
            'max_participants' => 10,
        ]);
        $activityB = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => now()->addDays(2),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'requires_approval' => false,
            'max_participants' => 10,
        ]);

        $service = app(ActivityParticipationService::class);

        $service->join($activityA, $user);
        $service->leave($activityA->fresh(), $user);
        $service->join($activityA->fresh(), $user);

        $response = $service->join($activityB, $user);

        $this->assertSame(__('You joined the activity.'), $response->getSession()->get('status'));
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $activityB->id)
                ->where('user_id', $user->id)
                ->exists()
        );
    }
}
