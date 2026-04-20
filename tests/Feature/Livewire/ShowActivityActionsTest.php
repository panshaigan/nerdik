<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowActivityActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_to_waitlist_executes_only_after_confirmation(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => true,
        ]);

        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('confirmMoveParticipantToWaitlist', $participant->id)
            ->assertSet('confirmModalOpen', true)
            ->call('runConfirmedAction')
            ->assertSet('confirmModalOpen', false);

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
        $this->assertTrue(
            ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_join_action_joins_without_http_form_post(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => false,
            'max_participants' => 5,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join');

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_interest_toggle_actions_update_interest_relations(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('addInterest');

        $this->assertTrue($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('removeInterest');

        $this->assertFalse($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
    }
}
