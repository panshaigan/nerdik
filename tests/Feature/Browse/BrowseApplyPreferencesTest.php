<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Models\UserPlayArea;
use App\Services\UserPlayPreferencesService;
use App\Support\Browse\BrowseSearchState;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BrowseApplyPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_user_play_preferences_builds_url_with_tags_and_bbox(): void
    {
        $user = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Catan',
        ]);
        $user->interestTags()->sync([$tag->id]);
        UserPlayArea::query()->create([
            'user_id' => $user->id,
            'latitude' => 52.2297,
            'longitude' => 21.0122,
            'radius_km' => 25,
            'label' => 'Warsaw',
        ]);

        $url = BrowseSearchUrl::fromUserPlayPreferences($user);

        $this->assertNotNull($url);
        $this->assertStringContainsString('tag_ids', $url);
        $this->assertStringContainsString((string) $tag->id, $url);
        $this->assertStringContainsString('min_lat=', $url);
        $this->assertStringContainsString('map_view=1', $url);
    }

    public function test_from_user_play_preferences_returns_null_when_empty(): void
    {
        $user = User::factory()->create();

        $this->assertNull(BrowseSearchUrl::fromUserPlayPreferences($user));
    }

    public function test_apply_my_preferences_clears_filters_and_sets_tags_and_bbox(): void
    {
        $user = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Catan',
        ]);
        app(UserPlayPreferencesService::class)->syncInterestTags($user, [(int) $tag->id]);
        app(UserPlayPreferencesService::class)->savePlayArea($user, 52.2297, 21.0122, 25, 'Warsaw');

        $state = BrowseSearchUrl::playPreferencesFilterState($user);
        $this->assertNotNull($state);

        Livewire::actingAs($user)
            ->test(BrowseEvents::class, ['q' => 'noise'])
            ->call('saveSearchParams')
            ->call('applyMyPreferences')
            ->assertSet('q', '')
            ->assertSet('tag_ids', array_map('intval', $state['bag']->tagIds))
            ->assertSet('map_view', true)
            ->assertSet('min_lat', $state['bag']->minLat);

        $this->assertNull(session(BrowseSearchState::SESSION_KEY));
    }

    public function test_apply_my_preferences_without_profile_prefs_shows_warning_and_keeps_filters(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BrowseEvents::class, ['q' => 'keep-me'])
            ->call('applyMyPreferences')
            ->assertHasNoErrors()
            ->assertSet('q', 'keep-me');
    }

    public function test_apply_my_preferences_requires_authentication(): void
    {
        Livewire::test(BrowseEvents::class)
            ->call('applyMyPreferences')
            ->assertForbidden();
    }

    public function test_apply_preferences_button_hidden_for_guests(): void
    {
        Livewire::test(BrowseEvents::class)
            ->assertDontSeeHtml('data-ui="browse-events-apply-preferences"');
    }
}
