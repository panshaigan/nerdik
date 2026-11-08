<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Livewire\Groups\GroupLocationForm;
use App\Livewire\Groups\GroupTagsForm;
use App\Livewire\Groups\ShowGroup;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Services\GroupDiscoveryPreferencesService;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupDiscoveryPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_show_exposes_discovery_tabs(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Discovery Party');

        $this->actingAs($owner)
            ->get(route('groups.show', ['group' => $group, 'tab' => 'location']))
            ->assertOk()
            ->assertSeeHtml('data-ui="group-show-tabs"')
            ->assertSeeHtml('data-ui="group-show-tab-location"')
            ->assertSeeHtml('data-ui="group-show-tab-tags"');
    }

    public function test_owner_can_save_group_location(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Location Party');

        Livewire::actingAs($owner)
            ->test(GroupLocationForm::class, ['groupId' => $group->id])
            ->set('playLatitude', 52.2297)
            ->set('playLongitude', 21.0122)
            ->set('playRadiusKm', 40)
            ->set('playLabel', 'Warsaw')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('group-location-updated');

        $group->refresh();

        $this->assertTrue($group->hasLocation());
        $this->assertSame(40, (int) $group->radius_km);
        $this->assertSame('Warsaw', $group->location_label);
    }

    public function test_owner_can_clear_group_location(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Clear Location');
        app(GroupDiscoveryPreferencesService::class)->saveLocation(
            $group,
            $owner,
            52.0,
            21.0,
            25,
            'Warsaw',
        );

        Livewire::actingAs($owner)
            ->test(GroupLocationForm::class, ['groupId' => $group->id])
            ->call('setPlayAreaDraft', null, null, 25, null)
            ->call('save')
            ->assertHasNoErrors();

        $group->refresh();

        $this->assertFalse($group->hasLocation());
        $this->assertNull($group->location_label);
    }

    public function test_owner_can_save_group_tags(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Tagged Party');
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_MECHANIC]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Co-op',
        ]);

        Livewire::actingAs($owner)
            ->test(GroupTagsForm::class, ['groupId' => $group->id])
            ->set('tag_ids', [(int) $tag->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('group-tags-updated');

        $this->assertSame([(int) $tag->id], $group->fresh()->tags->pluck('id')->all());
    }

    public function test_member_cannot_save_group_location(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Member Locked');
        $group->users()->attach($member->id);

        Livewire::actingAs($member)
            ->test(GroupLocationForm::class, ['groupId' => $group->id])
            ->set('playLatitude', 52.2297)
            ->set('playLongitude', 21.0122)
            ->set('playRadiusKm', 30)
            ->call('save');

        $this->assertFalse($group->fresh()->hasLocation());
    }

    public function test_tab_query_string_switches_to_members(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Tab Party');

        Livewire::actingAs($owner)
            ->withQueryParams(['tab' => 'members'])
            ->test(ShowGroup::class, ['groupId' => $group->id])
            ->assertSet('tab', 'members')
            ->assertSet('mountedTabs', ['members']);
    }
}
