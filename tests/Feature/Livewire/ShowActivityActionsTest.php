<?php

namespace Tests\Feature\Livewire;

use App\Enums\ParticipationMode;
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
            'participation_mode' => ParticipationMode::HostApproval,
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
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join');

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_guest_sees_join_login_link_on_participation_tab(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'starts_at' => now()->addDay(),
        ]);

        $returnPath = route('activities.show', ['activity' => $activity, 'tab' => 'participation'], false);
        $loginHref = login_url($returnPath);

        // Both tab panels stay mounted (Alpine x-show); tab switches skipRender.
        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertViewHas('canPromptGuestJoin', true)
            ->assertSeeHtml('data-ui="activity-show-guest-join"')
            ->assertSee($loginHref, false)
            ->assertDontSeeHtml('wire:click="join"');
    }

    public function test_guest_sees_join_waitlist_login_link_when_activity_is_full(): void
    {
        $host = User::factory()->create();
        $filler = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 1,
            'starts_at' => now()->addDay(),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $filler->id,
        ]);

        // Both tab panels stay mounted (Alpine x-show); tab switches skipRender.
        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertViewHas('canPromptGuestJoin', true)
            ->assertSeeHtml('data-ui="activity-show-guest-join-waitlist"')
            ->assertDontSeeHtml('data-ui="activity-show-guest-join"')
            ->assertDontSeeHtml('wire:click="joinWaitlist"');
    }

    public function test_remove_participant_executes_only_after_confirmation(): void
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
        ]);

        $component = Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('confirmRemoveParticipant', $participant->id)
            ->assertSet('confirmModalOpen', true);

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );

        $component
            ->call('runConfirmedAction')
            ->assertSet('confirmModalOpen', false);

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_interest_toggle_actions_update_interest_relations(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create();

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('addInterest');

        $this->assertTrue($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('removeInterest');

        $this->assertFalse($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
    }

    public function test_toolbar_interest_button_includes_wire_click_when_authenticated(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create();

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSeeHtml('data-ui="activity-show-interest-add"')
            ->assertSeeHtml('wire:click="addInterest"');
    }

    public function test_guest_sees_follow_count_without_toggle(): void
    {
        $activity = Activity::factory()->selfHosted()->create();

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringContainsString('data-ui="activity-show-interest-count"', $html);
        $this->assertStringNotContainsString('data-ui="activity-show-interest-add"', $html);
        $this->assertStringNotContainsString('data-ui="activity-show-interest-remove"', $html);
        $this->assertStringNotContainsString('wire:click="addInterest"', $html);
        $this->assertStringNotContainsString('wire:click="removeInterest"', $html);
    }

    public function test_toolbar_interest_buttons_toggle_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create();

        $component = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSeeHtml('data-ui="activity-show-interest-add"')
            ->assertDontSeeHtml('data-ui="activity-show-interest-remove"');

        $component->call('addInterest')
            ->assertSeeHtml('data-ui="activity-show-interest-remove"')
            ->assertDontSeeHtml('data-ui="activity-show-interest-add"');

        $component->call('removeInterest')
            ->assertSeeHtml('data-ui="activity-show-interest-add"')
            ->assertDontSeeHtml('data-ui="activity-show-interest-remove"');
    }

    public function test_toolbar_interest_buttons_are_hidden_for_guests(): void
    {
        $activity = Activity::factory()->selfHosted()->create();

        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertDontSeeHtml('data-ui="activity-show-interest-add"')
            ->assertDontSeeHtml('data-ui="activity-show-interest-remove"')
            ->assertSeeHtml('data-ui="activity-show-interest-count"');
    }

    public function test_toolbar_follow_count_updates_after_interest_toggle(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create();

        $component = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSeeHtml('data-count="0"');

        $component->call('addInterest')
            ->assertSeeHtml('data-count="1"');

        $component->call('removeInterest')
            ->assertSeeHtml('data-count="0"');
    }

    public function test_join_leave_buttons_render_with_spinner_attributes(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertSeeHtml('wire:target="join"')
            ->assertSeeHtml('wire:loading.attr="disabled"');

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertDontSeeHtml('wire:target="join"')
            ->assertDontSeeHtml('wire:target="joinWaitlist"');

        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertSeeHtml('wire:target="confirmRemoveParticipant('.$participant->id.')"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }
}
