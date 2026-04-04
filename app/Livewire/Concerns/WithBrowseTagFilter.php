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
     * @var list<array{label: string, category: string}>
     */
    public array $new_tags = [];

    public function updatedTagIds(): void
    {
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
