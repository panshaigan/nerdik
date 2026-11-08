<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Livewire\Groups\GroupMembersList;
use App\Livewire\Groups\ShowGroup;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Campaign Alpha');
        $group->users()->attach($member->id);

        $this->actingAs($member)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSeeHtml('data-ui="group-show-tabs"')
            ->assertSeeHtml('data-ui="group-show-tab-calendar"')
            ->assertSeeHtml('data-ui="group-show-tab-members"');
    }

    public function test_non_member_cannot_view_group(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Secret Party');

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertNotFound();
    }

    public function test_show_group_uses_locked_group_id(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Locked Id');

        Livewire::actingAs($owner)
            ->test(ShowGroup::class, ['groupId' => $group->id])
            ->assertSet('groupId', $group->id)
            ->assertSet('tab', 'calendar')
            ->assertSet('mountedTabs', ['calendar']);
    }

    public function test_members_tab_lists_active_members_ordered_by_nickname(): void
    {
        $owner = User::factory()->create(['nickname' => 'zeta_owner']);
        $member = User::factory()->create(['nickname' => 'alpha_member']);
        $group = app(GroupMembershipService::class)->create($owner, 'Roster');
        $group->users()->attach($member->id);

        Livewire::actingAs($owner)
            ->test(GroupMembersList::class, ['groupId' => $group->id])
            ->assertSeeInOrder(['alpha_member', 'zeta_owner']);
    }

    public function test_owner_can_remove_member_from_members_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Kick Party');
        $group->users()->attach($member->id);

        Livewire::actingAs($owner)
            ->test(GroupMembersList::class, ['groupId' => $group->id])
            ->call('removeMember', $member->id)
            ->assertHasNoErrors();

        $this->assertFalse($group->fresh()->users()->whereKey($member->id)->exists());
    }
}
