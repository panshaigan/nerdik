<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Enums\GroupVisibility;
use App\Livewire\Groups\GroupAvailabilityCalendar;
use App\Livewire\Groups\GroupFindDirectory;
use App\Livewire\Groups\ShowGroup;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupMembershipService;
use App\Support\Search\GlobalNavSearch as GlobalNavSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_join_adds_member(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $group = Group::factory()->public()->withOwnerMember()->create(['created_by' => $owner->id]);

        app(GroupMembershipService::class)->join($group, $joiner);

        $this->assertTrue($group->fresh()->users()->whereKey($joiner->id)->exists());
    }

    public function test_private_join_is_rejected(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Private crew');

        $this->expectException(ValidationException::class);
        app(GroupMembershipService::class)->join($group, $joiner);
    }

    public function test_owner_can_toggle_visibility_without_dropping_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Toggle test');
        $group->users()->attach($member->id);

        app(GroupMembershipService::class)->updateVisibility($group, $owner, GroupVisibility::Public);
        app(GroupMembershipService::class)->updateVisibility($group->fresh(), $owner, GroupVisibility::Private);

        $group = $group->fresh();
        $this->assertSame(GroupVisibility::Private, $group->visibility);
        $this->assertTrue($group->users()->whereKey($member->id)->exists());
    }

    public function test_non_owner_cannot_change_visibility(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Locked visibility');
        $group->users()->attach($member->id);

        $this->expectException(ValidationException::class);
        app(GroupMembershipService::class)->updateVisibility($group, $member, GroupVisibility::Public);
    }

    public function test_private_group_stays_hidden_from_non_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Secret Party');

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertNotFound();
    }

    public function test_public_group_shows_join_card_without_member_tabs(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::factory()->public()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'Open Tabletop',
        ]);

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSeeHtml('data-ui="group-public-card"')
            ->assertSeeHtml('data-ui="group-join"')
            ->assertDontSeeHtml('data-ui="group-show-tabs"');
    }

    public function test_join_on_public_group_reveals_calendar_tab(): void
    {
        $owner = User::factory()->create();
        $joiner = User::factory()->create();
        $group = Group::factory()->public()->withOwnerMember()->create(['created_by' => $owner->id]);

        Livewire::actingAs($joiner)
            ->test(ShowGroup::class, ['groupId' => $group->id])
            ->call('join')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-ui="group-show-tabs"')
            ->assertSeeHtml('data-ui="group-show-tab-calendar"');
    }

    public function test_public_non_member_cannot_mount_calendar_component(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::factory()->public()->withOwnerMember()->create(['created_by' => $owner->id]);

        $this->actingAs($outsider);

        Livewire::test(GroupAvailabilityCalendar::class, ['groupId' => $group->id])
            ->assertStatus(404);
    }

    public function test_find_directory_lists_public_group_and_skips_private_and_mine(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $public = Group::factory()->public()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'Public Listing Alpha',
        ]);

        Group::factory()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'Private Listing Beta',
        ]);

        $mine = Group::factory()->public()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'Already Joined Public',
        ]);
        $mine->users()->attach($viewer->id);

        Livewire::actingAs($viewer)
            ->test(GroupFindDirectory::class)
            ->assertSee('Public Listing Alpha')
            ->assertDontSee('Private Listing Beta')
            ->assertDontSee('Already Joined Public');
    }

    public function test_global_nav_search_returns_public_groups_for_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        Group::factory()->public()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'NavSearch Public Group',
        ]);

        Group::factory()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'NavSearch Private Group',
        ]);

        $results = GlobalNavSearchService::search('NavSearch Public', $viewer);
        $labels = collect($results['groups'])->pluck('label')->all();

        $this->assertContains('NavSearch Public Group', $labels);
        $this->assertNotContains('NavSearch Private Group', $labels);
    }

    public function test_global_nav_search_skips_groups_for_guests(): void
    {
        $owner = User::factory()->create();
        Group::factory()->public()->withOwnerMember()->create([
            'created_by' => $owner->id,
            'name' => 'Guest Nav Group',
        ]);

        $results = GlobalNavSearchService::search('Guest Nav', null);
        $this->assertSame([], $results['groups']);
    }
}
