<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseTagSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_preload_excludes_trigger_category_even_when_most_popular(): void
    {
        $triggerCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_TRIGGER]);
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);

        $trigger = Tag::factory()->create(['tag_category_id' => $triggerCategory->id]);
        $game = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);

        $this->attachTagToBrowseVisibleActivity($trigger, 100);
        $this->attachTagToBrowseVisibleActivity($game, 1);

        /** @var Collection<int, Tag> $tags */
        $tags = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->viewData('tags');

        $this->assertTrue($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $game->id));
        $this->assertFalse($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $trigger->id));
    }

    public function test_browse_preload_includes_game_tag_when_other_category_is_more_popular(): void
    {
        $otherCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_OTHER]);
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);

        $game = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $this->attachTagToBrowseVisibleActivity($game, 50);

        for ($i = 0; $i < 10; $i++) {
            $other = Tag::factory()->create(['tag_category_id' => $otherCategory->id]);
            $this->attachTagToBrowseVisibleActivity($other, 100 - $i);
        }

        /** @var Collection<int, Tag> $tags */
        $tags = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->viewData('tags');

        $this->assertTrue($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $game->id));
    }

    public function test_browse_selector_includes_selected_tag_outside_preload(): void
    {
        $category = TagCategory::factory()->create(['key' => 'browse-selected-outside-preload']);
        $selected = Tag::factory()->create(['tag_category_id' => $category->id]);
        $this->attachTagToBrowseVisibleActivity($selected, 1);

        for ($i = 0; $i < 8; $i++) {
            $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
            $this->attachTagToBrowseVisibleActivity($tag, 100 - $i);
        }

        /** @var Collection<int, Tag> $tags */
        $tags = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, ['tag_ids' => [(int) $selected->id]])
            ->viewData('tags');

        $this->assertTrue($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $selected->id));
    }

    public function test_search_browse_tags_finds_tag_outside_preload(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $otherCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_OTHER]);

        $vampire = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $vampire->id,
            'locale' => 'en',
            'label' => 'Vampire: The Masquerade',
            'slug' => 'vampire-the-masquerade',
        ]);
        $this->attachTagToBrowseVisibleActivity($vampire, 1);

        for ($i = 0; $i < 8; $i++) {
            $other = Tag::factory()->create(['tag_category_id' => $otherCategory->id]);
            $this->attachTagToBrowseVisibleActivity($other, 100 - $i);
        }

        $component = Livewire::withoutLazyLoading()->test(BrowseEvents::class);
        $results = $component->instance()->searchBrowseTags('vamp');

        $this->assertIsArray($results);
        $ids = array_map(static fn (array $row) => (int) $row['id'], $results);
        $this->assertContains((int) $vampire->id, $ids);
    }

    public function test_search_browse_tags_matches_typo_via_trigram_similarity(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);

        $vampire = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $vampire->id,
            'locale' => 'en',
            'label' => 'Vampire',
            'slug' => 'vampire',
        ]);
        $this->attachTagToBrowseVisibleActivity($vampire, 5);

        $component = Livewire::withoutLazyLoading()->test(BrowseEvents::class);
        $results = $component->instance()->searchBrowseTags('vamprie');

        $this->assertIsArray($results);
        $ids = array_map(static fn (array $row) => (int) $row['id'], $results);
        $this->assertContains((int) $vampire->id, $ids);
    }

    public function test_search_browse_tags_prefers_exact_match_over_fuzzy_match(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);

        $exact = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $exact->id,
            'locale' => 'en',
            'label' => 'Vampire',
            'slug' => 'vampire',
        ]);
        $this->attachTagToBrowseVisibleActivity($exact, 1);

        $fuzzy = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $fuzzy->id,
            'locale' => 'en',
            'label' => 'Vamspire',
            'slug' => 'vamspire',
        ]);
        $this->attachTagToBrowseVisibleActivity($fuzzy, 100);

        $component = Livewire::withoutLazyLoading()->test(BrowseEvents::class);
        $results = $component->instance()->searchBrowseTags('vampire');

        $this->assertIsArray($results);
        $ids = array_map(static fn (array $row) => (int) $row['id'], $results);
        $this->assertSame((int) $exact->id, $ids[0] ?? null);
        $this->assertContains((int) $fuzzy->id, $ids);
    }

    public function test_browse_events_renders_tag_selector_with_browse_suggestion_config(): void
    {
        $html = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->html();

        $normalized = preg_replace('/\s+/', '', $html) ?? $html;

        $this->assertStringContainsString('data-ts-config', $html);
        $this->assertStringContainsString('"maxPerCategory":7', $normalized);
        $this->assertStringContainsString('"searchLimit":30', $normalized);
    }

    public function test_browse_suggestions_include_events_and_activities(): void
    {
        $event = Event::factory()->public()->create([
            'name' => 'Vampire Weekend',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(5),
            'cancelled_at' => null,
        ]);

        $activity = Activity::factory()->create([
            'name' => 'Vampire Workshop',
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
            'cancelled_at' => null,
        ]);

        $component = Livewire::withoutLazyLoading()->test(BrowseEvents::class);
        $results = $component->instance()->searchBrowseSuggestions('vamp');

        $this->assertIsArray($results);

        $eventIds = array_map(static fn (array $row) => (int) $row['id'], $results['events'] ?? []);
        $this->assertContains((int) $event->id, $eventIds);

        $activityIds = array_map(static fn (array $row) => (int) $row['id'], $results['activities'] ?? []);
        $this->assertContains((int) $activity->id, $activityIds);

        $eventRow = null;
        foreach (($results['events'] ?? []) as $row) {
            if ((int) $row['id'] === (int) $event->id) {
                $eventRow = $row;
                break;
            }
        }
        $this->assertNotNull($eventRow, 'Expected events suggestion row to exist.');
        $this->assertSame(route('events.show', $event), (string) $eventRow['url']);

        $activityRow = null;
        foreach (($results['activities'] ?? []) as $row) {
            if ((int) $row['id'] === (int) $activity->id) {
                $activityRow = $row;
                break;
            }
        }
        $this->assertNotNull($activityRow, 'Expected activities suggestion row to exist.');
        $this->assertSame(route('activities.show', $activity), (string) $activityRow['url']);
    }

    private function attachTagToBrowseVisibleActivity(Tag $tag, int $popularityScore): void
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
        Tag::query()->whereKey($tag->id)->update(['popularity_score' => $popularityScore]);
    }
}
