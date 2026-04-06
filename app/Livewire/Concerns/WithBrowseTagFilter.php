<?php

namespace App\Livewire\Concerns;

use App\Models\Tag;
use App\Support\BrowseTagFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

trait WithBrowseTagFilter
{
    /** @var list<int|string> */
    #[Url]
    public array $tag_ids = [];

    #[Url]
    public bool $tags_match_all = false;

    /**
     * Kept in sync by {@see resources/js/tags-selector.js} for the shared tag selector (browse uses allowCreate=false).
     *
     * @var list<array{label: string, category_id: int|string}>
     */
    public array $new_tags = [];

    public function updatedTagIds(): void
    {
        $this->resetPage();
    }

    /**
     * Called from {@see resources/js/tags-selector.js} for browse (data-browse-tag-selector).
     * Uses a dedicated method so tag filter updates reliably with URL-bound {@see $tag_ids}.
     *
     * @param  list<int|string>  $tag_ids
     * @param  list<array{label: string, category_id: int|string}>  $new_tags
     */
    public function syncBrowseTagsFromSelector(array $tag_ids, array $new_tags = []): void
    {
        $this->tag_ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tag_ids),
            static fn (int $id) => $id > 0
        )));

        $this->new_tags = collect($new_tags)
            ->filter(static fn ($row) => is_array($row) && isset($row['label'], $row['category_id']))
            ->map(static fn (array $row) => [
                'label' => trim((string) $row['label']),
                'category_id' => (int) $row['category_id'],
            ])
            ->values()
            ->all();

        $this->resetPage();
    }

    public function updatedTagsMatchAll(): void
    {
        $this->resetPage();
    }

    protected function resetTagFilter(): void
    {
        $this->tag_ids = [];
        $this->tags_match_all = false;
        $this->new_tags = [];
    }

    protected function hasTagFilterActive(): bool
    {
        return array_filter(array_map('intval', $this->tag_ids), fn (int $id) => $id > 0) !== [];
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applyBrowseTagFilter(Builder $query, string $relation = 'tags'): void
    {
        BrowseTagFilter::apply(
            $query,
            array_map('intval', $this->tag_ids),
            $this->tags_match_all,
            $relation
        );
    }
}
