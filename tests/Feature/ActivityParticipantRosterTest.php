<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityParticipantRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_unmark_absent(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
            'is_absent' => true,
        ]);

        $this->actingAs($host);
        $response = $this->post(route('activity-participants.unmark-absent', $participant));

        $response->assertRedirect();
        $this->assertFalse($participant->fresh()->is_absent);
    }

    public function test_non_host_cannot_unmark_absent(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
            'is_absent' => true,
        ]);

        $this->actingAs($other);
        $this->post(route('activity-participants.unmark-absent', $participant))->assertForbidden();
        $this->assertTrue($participant->fresh()->is_absent);
    }

    public function test_host_can_move_participant_to_waitlist_without_auto_promoting_others(): void
    {
        $host = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => true,
            'max_participants' => 2,
        ]);

        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $carol->id,
            'position' => 1,
        ]);

        $aliceRow = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $alice->id)
            ->firstOrFail();

        $this->actingAs($host);
        $this->post(route('activity-participants.move-to-waitlist', $aliceRow))->assertRedirect();

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $alice->id)->exists()
        );
        $this->assertTrue(
            ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $alice->id)->exists()
        );
        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $bob->id)->exists()
        );
        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $carol->id)->exists(),
            'Waitlist should not be auto-promoted when the host moves someone to the waitlist.'
        );
        $this->assertSame(2, (int) ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->count());
    }

    public function test_cannot_move_host_to_waitlist(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $hostRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $host->id,
        ]);

        $this->actingAs($host);
        $this->post(route('activity-participants.move-to-waitlist', $hostRow))->assertRedirect();

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $host->id)->exists()
        );
        $this->assertSame(0, ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->count());
    }

    public function test_non_host_cannot_move_participant_to_waitlist(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $intruder = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $memberRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($intruder);
        $this->post(route('activity-participants.move-to-waitlist', $memberRow))->assertForbidden();
    }

    public function test_move_to_waitlist_is_blocked_when_activity_cancelled(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'cancelled_at' => now(),
            'cancelled_by' => $host->id,
        ]);
        $memberRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($host);
        $this->post(route('activity-participants.move-to-waitlist', $memberRow))->assertRedirect();

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_host_can_remove_participant(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $memberRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($host);
        $this->post(route('activity-participants.remove', $memberRow))->assertRedirect();

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_non_host_cannot_remove_participant(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $intruder = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $memberRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($intruder);
        $this->post(route('activity-participants.remove', $memberRow))->assertForbidden();
    }

    public function test_cannot_remove_host_from_participants(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $hostRow = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $host->id,
        ]);

        $this->actingAs($host);
        $this->post(route('activity-participants.remove', $hostRow))->assertRedirect();

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $host->id)->exists()
        );
    }
}
