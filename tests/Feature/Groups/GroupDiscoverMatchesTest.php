<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Livewire\Groups\GroupDiscoverMatches;
use App\Livewire\Groups\ShowGroup;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Services\GroupCandidateDiscoveryService;
use App\Services\GroupDiscoveryPreferencesService;
use App\Services\GroupMembershipService;
use App\Services\UserPlayPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupDiscoverMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_match_shared_tags_only(): void
    {
        $owner = User::factory()->create();
        $match = User::factory()->create(['nickname' => 'tag_match_user']);
        $other = User::factory()->create(['nickname' => 'tag_other_user']);

        $group = app(GroupMembershipService::class)->create($owner, 'Tag Party');
        $tag = $this->createTag('Co-op');
        $otherTag = $this->createTag('Solo');

        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($match, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($other, [(int) $otherTag->id]);

        $page = app(GroupCandidateDiscoveryService::class)
            ->candidates($group->fresh(['tags']), $owner);

        $ids = $page->pluck('id')->all();

        $this->assertContains($match->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertNotContains($owner->id, $ids);

        $matchedUser = $page->firstWhere('id', $match->id);
        $this->assertNotNull($matchedUser);
        $this->assertSame(['Co-op'], $matchedUser->discovery_match_tag_labels);
        $this->assertNull($matchedUser->discovery_match_play_area_caption);
    }

    public function test_candidates_match_overlapping_play_areas(): void
    {
        $owner = User::factory()->create();
        $nearby = User::factory()->create();
        $far = User::factory()->create();

        $group = app(GroupMembershipService::class)->create($owner, 'Geo Party');
        app(GroupDiscoveryPreferencesService::class)->saveLocation(
            $group,
            $owner,
            52.2297,
            21.0122,
            25,
            'Warsaw',
        );

        app(UserPlayPreferencesService::class)->savePlayArea($nearby, 52.24, 21.02, 25, null);
        app(UserPlayPreferencesService::class)->savePlayArea($far, 50.0, 14.0, 25, null);

        $page = app(GroupCandidateDiscoveryService::class)
            ->candidates($group->fresh(), $owner);

        $ids = $page->pluck('id')->all();

        $this->assertContains($nearby->id, $ids);
        $this->assertNotContains($far->id, $ids);

        $nearbyUser = $page->firstWhere('id', $nearby->id);
        $this->assertNotNull($nearbyUser);
        $this->assertSame([], $nearbyUser->discovery_match_tag_labels);
        $this->assertNotNull($nearbyUser->discovery_match_play_area_caption);
    }

    public function test_play_area_caption_includes_street_and_city(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create();
        $city = $this->createCity('Warsaw');
        $group = app(GroupMembershipService::class)->create($owner, 'Caption Party');

        app(GroupDiscoveryPreferencesService::class)->saveLocation(
            $group,
            $owner,
            52.2297,
            21.0122,
            25,
            'Group center',
        );
        app(UserPlayPreferencesService::class)->savePlayArea(
            $candidate,
            52.24,
            21.02,
            25,
            'Przemysłowa 7',
            $city->id,
        );

        $caption = app(GroupCandidateDiscoveryService::class)
            ->matchingPlayAreaCaption($group->fresh(), $candidate->fresh()->playArea);

        $this->assertNotNull($caption);
        $this->assertStringContainsString('Przemysłowa 7, Warsaw', $caption);
        $this->assertStringContainsString('25', $caption);
    }

    public function test_candidates_require_tags_and_location_when_both_set(): void
    {
        $owner = User::factory()->create();
        $fullMatch = User::factory()->create();
        $tagOnly = User::factory()->create();
        $geoOnly = User::factory()->create();

        $group = app(GroupMembershipService::class)->create($owner, 'And Party');
        $tag = $this->createTag('RPG');

        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(GroupDiscoveryPreferencesService::class)->saveLocation($group, $owner, 52.2297, 21.0122, 30, null);

        app(UserPlayPreferencesService::class)->syncInterestTags($fullMatch, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->savePlayArea($fullMatch, 52.24, 21.02, 25, null);

        app(UserPlayPreferencesService::class)->syncInterestTags($tagOnly, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->savePlayArea($geoOnly, 52.24, 21.02, 25, null);

        $ids = app(GroupCandidateDiscoveryService::class)
            ->candidates($group->fresh(['tags']), $owner)
            ->pluck('id')
            ->all();

        $this->assertContains($fullMatch->id, $ids);
        $this->assertNotContains($tagOnly->id, $ids);
        $this->assertNotContains($geoOnly->id, $ids);
    }

    public function test_existing_members_are_excluded_from_candidates(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Members');
        $group->users()->attach($member->id);

        $tag = $this->createTag('Shared');
        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($member, [(int) $tag->id]);

        $ids = app(GroupCandidateDiscoveryService::class)
            ->candidates($group->fresh(['tags']), $owner)
            ->pluck('id')
            ->all();

        $this->assertNotContains($member->id, $ids);
    }

    public function test_non_manager_receives_empty_candidates(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Locked');
        $group->users()->attach($member->id);

        $tag = $this->createTag('Open');
        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($outsider, [(int) $tag->id]);

        $page = app(GroupCandidateDiscoveryService::class)
            ->candidates($group->fresh(['tags']), $member);

        $this->assertCount(0, $page);
    }

    public function test_owner_sees_discover_tab_and_matching_user(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['nickname' => 'discover_me']);
        $group = app(GroupMembershipService::class)->create($owner, 'Discover UI');

        $tag = $this->createTag('LFG');
        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($candidate, [(int) $tag->id]);

        $this->actingAs($owner)
            ->get(route('groups.show', ['group' => $group, 'tab' => 'discover']))
            ->assertOk()
            ->assertSeeHtml('data-ui="group-show-tab-discover"')
            ->assertSee('discover_me')
            ->assertSee('LFG');
    }

    public function test_member_does_not_see_discover_tab(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Member view');
        $group->users()->attach($member->id);

        $this->actingAs($member)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertDontSeeHtml('data-ui="group-show-tab-discover"');
    }

    public function test_discover_tab_query_resets_for_non_manager(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Tab guard');
        $group->users()->attach($member->id);

        Livewire::actingAs($member)
            ->withQueryParams(['tab' => 'discover'])
            ->test(ShowGroup::class, ['groupId' => $group->id])
            ->assertSet('tab', 'calendar');
    }

    public function test_invite_from_discover_tab_creates_pending_request(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Invite flow');
        $tag = $this->createTag('Party');

        app(GroupDiscoveryPreferencesService::class)->syncTags($group, $owner, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->syncInterestTags($candidate, [(int) $tag->id]);

        Livewire::actingAs($owner)
            ->test(GroupDiscoverMatches::class, ['groupId' => $group->id])
            ->call('invite', $candidate->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_requests', [
            'type' => UserRequestType::GroupInvite->value,
            'status' => UserRequestStatus::Pending->value,
            'requester_id' => $owner->id,
            'recipient_id' => $candidate->id,
            'subject_type' => 'group',
            'subject_id' => $group->id,
        ]);

        Livewire::actingAs($owner)
            ->test(GroupDiscoverMatches::class, ['groupId' => $group->id])
            ->assertSee(__('ui.groups.discover_invited'));
    }

    private function createTag(string $label): Tag
    {
        $category = TagCategory::query()->where('key', TagCategory::KEY_MECHANIC)->first()
            ?? TagCategory::factory()->create(['key' => TagCategory::KEY_MECHANIC]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => $label,
        ]);

        return $tag;
    }

    private function createCity(string $enName): City
    {
        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create(['country_id' => $country->id]);
        CityTranslation::query()->create([
            'city_id' => $city->id,
            'locale' => 'en',
            'name' => $enName,
        ]);

        return $city;
    }
}
