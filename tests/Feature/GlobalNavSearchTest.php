<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Layout\GlobalNavSearch;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use App\Support\Search\GlobalNavSearch as GlobalNavSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalNavSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_public_event_and_activity_suggestions(): void
    {
        $owner = User::factory()->create();
        $publicEvent = Event::factory()->public()->create([
            'name' => 'Unique Nav Event Alpha',
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $activity = Activity::factory()->scheduled()->create([
            'name' => 'Unique Nav Activity Beta',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $results = GlobalNavSearchService::search('Unique Nav', null);

        $this->assertSame(route('events.show', $publicEvent), $results['events'][0]['url'] ?? null);
        $this->assertSame(route('activities.show', $activity), $results['activities'][0]['url'] ?? null);
    }

    public function test_private_event_hidden_from_guest_visible_to_creator(): void
    {
        $owner = User::factory()->create();
        $privateEvent = Event::factory()->create([
            'name' => 'Secret Nav Event Gamma',
            'is_public' => false,
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $guestResults = GlobalNavSearchService::search('Secret Nav Event', null);
        $this->assertSame([], $guestResults['events']);

        $ownerResults = GlobalNavSearchService::search('Secret Nav Event', $owner);
        $this->assertSame(route('events.show', $privateEvent), $ownerResults['events'][0]['url'] ?? null);
    }

    public function test_organization_preview_opens_from_suggestion(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Nav Search Org Delta',
        ]);

        Livewire::test(GlobalNavSearch::class)
            ->set('q', 'Nav Search Org')
            ->call('openOrganizationPreview', $organization->id)
            ->assertSet('organizationPreviewModalOpen', true)
            ->assertSet('previewOrganizationId', $organization->id);
    }

    public function test_user_preview_opens_and_deleted_users_are_excluded(): void
    {
        $user = User::factory()->create([
            'nickname' => 'navsearchuser',
        ]);

        $deleted = User::factory()->create([
            'nickname' => 'navsearchdeleted',
            'is_deleted' => true,
        ]);

        $results = GlobalNavSearchService::search('navsearch', null);
        $userIds = array_column($results['users'], 'id');
        $this->assertContains($user->id, $userIds);
        $this->assertNotContains($deleted->id, $userIds);

        Livewire::test(GlobalNavSearch::class)
            ->call('openUserPreview', $user->id)
            ->assertSet('userPreviewModalOpen', true)
            ->assertSet('previewUserId', $user->id);
    }

    public function test_place_suggestion_links_to_browse_search_for_venue(): void
    {
        $place = Place::factory()->venue()->create([
            'name' => 'Nav Search Venue Epsilon',
        ]);

        $results = GlobalNavSearchService::search('Nav Search Venue', null);

        $this->assertSame(BrowseSearchUrl::forPlace($place), $results['places'][0]['url'] ?? null);
    }

    public function test_tag_used_on_activity_is_suggested_unused_tag_is_not(): void
    {
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $usedTag = Tag::factory()->create(['tag_category_id' => $category->id]);
        TagTranslation::factory()->create([
            'tag_id' => $usedTag->id,
            'locale' => 'en',
            'label' => 'NavSearchUsedTag',
            'slug' => 'navsearchusedtag',
        ]);

        $unusedTag = Tag::factory()->create(['tag_category_id' => $category->id]);
        TagTranslation::factory()->create([
            'tag_id' => $unusedTag->id,
            'locale' => 'en',
            'label' => 'NavSearchUnusedTag',
            'slug' => 'navsearchunusedtag',
        ]);

        $this->attachTagToVisibleActivity($usedTag);

        $results = GlobalNavSearchService::search('NavSearchUsed', null);
        $tagIds = array_column($results['tags'], 'id');
        $this->assertContains($usedTag->id, $tagIds);
        $this->assertNotContains($unusedTag->id, $tagIds);

        $expectedUrl = route('search.index', ['tag_ids' => [$usedTag->id]], false);
        $this->assertSame($expectedUrl, $results['tags'][0]['url'] ?? null);
    }

    public function test_submit_search_redirects_to_search_page_with_query(): void
    {
        Livewire::test(GlobalNavSearch::class)
            ->set('q', '  board games  ')
            ->call('submitSearch')
            ->assertRedirect(route('search.index', ['q' => 'board games']));
    }

    public function test_livewire_renders_suggestion_labels_when_query_matches(): void
    {
        $owner = User::factory()->create();
        $activity = Activity::factory()->scheduled()->create([
            'name' => 'Nav Suggestion Visible Activity',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        Livewire::test(GlobalNavSearch::class)
            ->set('q', 'Nav Suggestion Visible')
            ->assertSee('Nav Suggestion Visible Activity')
            ->assertSee('data-ui="global-nav-search-suggestions"', false);
    }

    public function test_navigation_includes_global_search_component(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('data-ui="global-nav-search"', false);
    }

    public function test_dismiss_suggestions_hides_suggestion_panel(): void
    {
        $owner = User::factory()->create();
        $activity = Activity::factory()->scheduled()->create([
            'name' => 'Dismiss Panel Activity',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        Livewire::test(GlobalNavSearch::class)
            ->set('q', 'Dismiss Panel')
            ->assertSee('data-ui="global-nav-search-suggestions"', false)
            ->call('dismissSuggestions')
            ->assertDontSee('data-ui="global-nav-search-suggestions"', false)
            ->set('q', 'Dismiss Panel X')
            ->assertSee('data-ui="global-nav-search-suggestions"', false);
    }

    private function attachTagToVisibleActivity(Tag $tag): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $activity->tags()->attach($tag->id);
    }
}
