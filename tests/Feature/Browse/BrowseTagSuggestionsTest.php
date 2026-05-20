<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseTagSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_events_passes_top_fifty_tags_ordered_by_popularity(): void
    {
        $category = TagCategory::factory()->create(['key' => 'browse-popularity-cap']);

        $low = Tag::factory()->create(['tag_category_id' => $category->id]);
        Tag::query()->whereKey($low->id)->update(['popularity_score' => 1]);

        $high = Tag::factory()->create(['tag_category_id' => $category->id]);
        Tag::query()->whereKey($high->id)->update(['popularity_score' => 100]);

        for ($i = 0; $i < 50; $i++) {
            $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
            Tag::query()->whereKey($tag->id)->update(['popularity_score' => 10 + $i]);
        }

        /** @var Collection<int, Tag> $tags */
        $tags = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->viewData('tags');

        $this->assertCount(50, $tags);
        $this->assertSame((int) $high->id, (int) $tags->first()->id);
        $this->assertSame(100, (int) $tags->first()->popularity_score);
        $this->assertFalse($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $low->id));
    }

    public function test_browse_selector_includes_selected_tag_outside_top_fifty(): void
    {
        $category = TagCategory::factory()->create(['key' => 'browse-selected-outside-top']);

        $selected = Tag::factory()->create(['tag_category_id' => $category->id]);
        Tag::query()->whereKey($selected->id)->update(['popularity_score' => 1]);

        for ($i = 0; $i < 50; $i++) {
            $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
            Tag::query()->whereKey($tag->id)->update(['popularity_score' => 100 - $i]);
        }

        /** @var Collection<int, Tag> $tags */
        $tags = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, ['tag_ids' => [(int) $selected->id]])
            ->viewData('tags');

        $this->assertCount(51, $tags);
        $this->assertTrue($tags->contains(fn (Tag $tag) => (int) $tag->id === (int) $selected->id));
    }

    public function test_browse_events_renders_tag_selector_with_max_per_category_config(): void
    {
        $html = Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->html();

        $this->assertStringContainsString('data-ts-config', $html);
        $this->assertStringContainsString('"maxPerCategory":7', preg_replace('/\s+/', '', $html) ?? $html);
    }
}
