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

    public function test_interested_stat_includes_wire_click_when_authenticated(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $html = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->html();

        $opening = $this->interestedStatOpeningTag($html);
        $this->assertNotNull($opening);
        $this->assertStringContainsString('wire:click="addInterest"', $opening);
        $this->assertStringContainsString('wire:target="addInterest, removeInterest"', $opening);
        $this->assertStringContainsString('wire:loading.class.delay="pointer-events-none cursor-wait"', $opening);
        $this->assertStringContainsString('loading loading-spinner loading-sm', $html);
    }

    public function test_interested_stat_has_no_wire_click_for_guest(): void
    {
        $activity = Activity::factory()->create();

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $opening = $this->interestedStatOpeningTag($html);
        $this->assertNotNull($opening);
        $this->assertStringNotContainsString('wire:click', $opening);
        $this->assertStringNotContainsString('wire:target', $opening);
    }

    public function test_toolbar_interest_buttons_are_not_rendered(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertDontSeeHtml('data-ui="activity-show-interest-add"')
            ->assertDontSeeHtml('data-ui="activity-show-interest-remove"');
    }

    public function test_interested_stat_count_updates_after_interest_toggle(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity]);

        $component->assertSee('0');

        $component->call('addInterest');
        $component->assertSee('1');

        $component->call('removeInterest');
        $component->assertSee('0');
    }

    public function test_join_leave_buttons_render_with_spinner_attributes(): void
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
            ->set('tab', 'participation')
            ->assertSeeHtml('wire:target="join"')
            ->assertSeeHtml('wire:loading.attr="disabled"');

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

    /**
     * @return non-empty-string|null
     */
    private function interestedStatOpeningTag(string $html): ?string
    {
        if (preg_match('/<div\b[^>]*\bdata-ui="activity-show-interested-stat"[^>]*>/', $html, $matches) !== 1) {
            return null;
        }

        /** @var non-empty-string */
        return $matches[0];
    }
}
