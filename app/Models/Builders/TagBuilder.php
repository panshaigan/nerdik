<?php

namespace App\Models\Builders;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Additional scope methods for {@see Tag}.
 *
 * @extends Builder<Tag>
 */
class TagBuilder extends Builder
{
    public function ofCategory(string $categoryKey): self
    {
        $categoryId = TagCategory::idByKey($categoryKey);

        return $this->where('tag_category_id', $categoryId);
    }

    public function games(): self
    {
        return $this->ofCategory(TagCategory::KEY_GAME);
    }

    public function formats(): self
    {
        return $this->ofCategory(TagCategory::KEY_FORMAT);
    }

    public function triggers(): self
    {
        return $this->ofCategory(TagCategory::KEY_TRIGGER);
    }

    public function others(): self
    {
        return $this->ofCategory(TagCategory::KEY_OTHER);
    }

    public function withRelated(): self
    {
        return $this->with('relatedTags');
    }

    public function orderedForSelector(): self
    {
        return $this->with(['translations', 'aliases', 'tagRelations', 'tagCategory.translations'])
            ->orderBy('tag_category_id')
            ->orderBy('id');
    }

    /** Tags for activity form picker: includes contexts for suggestion filtering. */
    public function forActivityFormPicker(): self
    {
        return $this->with(['translations', 'aliases', 'tagRelations', 'tagCategory.translations', 'contexts'])
            ->orderBy('tag_category_id')
            ->orderBy('id');
    }

    public function orderedByPopularity(): self
    {
        return $this->orderByDesc('popularity_score')->orderBy('id');
    }

    public function forBrowseSuggestions(int $limit = 50): self
    {
        return $this
            ->with(['translations', 'aliases', 'tagCategory.translations'])
            ->where('popularity_score', '>', 0)
            ->orderedByPopularity()
            ->limit($limit);
    }

    /**
     * Top popular tags for browse selector plus any currently selected tags not in that set.
     *
     * @param  list<int|string>  $selectedIds
     * @return Collection<int, Tag>
     */
    public function forBrowseSelector(array $selectedIds, int $limit = 50): Collection
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $selectedIds),
            static fn (int $id) => $id > 0
        )));

        $popular = $this->getModel()->newQuery()->forBrowseSuggestions($limit)->get();

        $missing = array_values(array_diff($normalized, $popular->pluck('id')->map(fn ($id) => (int) $id)->all()));
        if ($missing === []) {
            return $popular;
        }

        $extra = $this->getModel()->newQuery()
            ->with(['translations', 'aliases', 'tagCategory.translations'])
            ->whereIn('id', $missing)
            ->orderBy('id')
            ->get();

        return $popular->merge($extra)->unique('id')->values();
    }
}
