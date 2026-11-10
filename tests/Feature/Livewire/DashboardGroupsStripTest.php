<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardGroupsStripTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_groups_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $membership = app(GroupMembershipService::class);
        $group = $membership->create($user, 'Dashboard Member Group');

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertViewHas('userGroupsCount', 1)
            ->assertSeeHtml('data-ui="dashboard-stat-my-groups"')
            ->assertDontSeeHtml('data-ui="dashboard-groups-panel"')
            ->call('toggleGroupsPanel')
            ->assertSeeHtml('data-ui="dashboard-groups-panel"')
            ->assertSee('Dashboard Member Group')
            ->assertSeeHtml('data-ui="dashboard-group-card"');
    }

    public function test_dashboard_does_not_list_other_users_private_groups(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        app(GroupMembershipService::class)->create($other, 'Secret Other Group');

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->call('toggleGroupsPanel')
            ->assertDontSee('Secret Other Group');
    }

    public function test_dashboard_suggests_a_public_group_when_user_has_none(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        Group::factory()->public()->withOwnerMember()->create([
            'name' => 'Suggested Public Crew',
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->call('toggleGroupsPanel')
            ->assertSeeHtml('data-ui="dashboard-group-suggested"')
            ->assertSee('Suggested Public Crew');
    }
}
