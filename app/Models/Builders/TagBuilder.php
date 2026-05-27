<?php

namespace App\Models\Builders;

use App\Models\Activity;
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

    public function usedOnBrowseVisibleActivities(bool $upcomingOnly = true): self
    {
        $activityMorph = (new Activity)->getMorphClass();

        return $this->whereIn($this->getModel()->getQualifiedKeyName(), function ($query) use ($activityMorph, $upcomingOnly): void {
            $query->select('tag_id')
                ->from('taggables')
                ->where('taggable_type', $activityMorph)
                ->whereIn('taggable_id', Activity::query()->attachedToPublicEvent($upcomingOnly)->select('activities.id'));
        });
    }

    /**
     * @param  list<string>  $categoryKeys
     */
    public function excludingCategories(array $categoryKeys): self
    {
        $ids = array_values(array_filter(array_map(
            static fn (string $key): ?int => TagCategory::idByKey($key),
            $categoryKeys
        )));

        if ($ids === []) {
            return $this;
        }

        return $this->whereNotIn('tag_category_id', $ids);
    }

    /**
     * @param  list<string>  $excludeCategoryKeys
     * @param  list<string>  $categoryOrder
     * @return Collection<int, Tag>
     */
    public function forBrowsePreloadSuggestions(
        int $perCategory,
        array $excludeCategoryKeys = [],
        array $categoryOrder = [],
    ): Collection {
        if ($categoryOrder === []) {
            $categoryOrder = config('browse.tag_suggestions.category_order', []);
        }

        $eager = ['translations', 'aliases', 'tagCategory.translations'];
        $tags = collect();

        foreach ($categoryOrder as $categoryKey) {
            if (in_array($categoryKey, $excludeCategoryKeys, true)) {
                continue;
            }

            $categoryId = TagCategory::idByKey($categoryKey);
            if ($categoryId === null) {
                continue;
            }

            $chunk = $this->getModel()->newQuery()
                ->with($eager)
                ->usedOnBrowseVisibleActivities(true)
                ->where('popularity_score', '>', 0)
                ->where('tag_category_id', $categoryId)
                ->orderedByPopularity()
                ->limit($perCategory)
                ->get();

            $tags = $tags->merge($chunk);
        }

        return $tags->unique('id')->values();
    }

    /**
     * @return Collection<int, Tag>
     */
    public function searchForBrowseSelector(string $query, bool $includePast, int $limit): Collection
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return collect();
        }

        $like = '%'.$normalized.'%';
        $similarityThreshold = 0.2;

        return $this->getModel()->newQuery()
            ->with(['translations', 'aliases', 'tagCategory.translations'])
            ->usedOnBrowseVisibleActivities(! $includePast)
            ->where(function (Builder $outer) use ($like, $normalized, $similarityThreshold): void {
                $outer->whereHas('translations', function (Builder $q) use ($like, $normalized, $similarityThreshold): void {
                    $q->where(function (Builder $inner) use ($like): void {
                        $inner->whereRaw('LOWER(label) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
                    })->orWhere(function (Builder $inner) use ($normalized, $similarityThreshold): void {
                        $inner->whereRaw('similarity(LOWER(label), ?) >= ?', [$normalized, $similarityThreshold])
                            ->orWhereRaw('similarity(LOWER(slug), ?) >= ?', [$normalized, $similarityThreshold]);
                    });
                })->orWhereHas('aliases', function (Builder $q) use ($like, $normalized, $similarityThreshold): void {
                    $q->whereRaw('LOWER(alias) LIKE ?', [$like])
                        ->orWhereRaw('similarity(LOWER(alias), ?) >= ?', [$normalized, $similarityThreshold]);
                });
            })
            ->orderByRaw(
                '
                (
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM tag_translations
                        WHERE tag_translations.tag_id = tags.id
                          AND (LOWER(tag_translations.label) = ? OR LOWER(tag_translations.slug) = ?)
                    ) THEN 2 ELSE 0 END
                    +
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM tag_aliases
                        WHERE tag_aliases.tag_id = tags.id
                          AND LOWER(tag_aliases.alias) = ?
                    ) THEN 2 ELSE 0 END
                    +
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM tag_translations
                        WHERE tag_translations.tag_id = tags.id
                          AND (LOWER(tag_translations.label) LIKE ? OR LOWER(tag_translations.slug) LIKE ?)
                    ) THEN 1 ELSE 0 END
                    +
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM tag_aliases
                        WHERE tag_aliases.tag_id = tags.id
                          AND LOWER(tag_aliases.alias) LIKE ?
                    ) THEN 1 ELSE 0 END
                ) DESC,
                GREATEST(
                    COALESCE((
                        SELECT MAX(
                            GREATEST(
                                similarity(LOWER(tag_translations.label), ?),
                                similarity(LOWER(tag_translations.slug), ?)
                            )
                        )
                        FROM tag_translations
                        WHERE tag_translations.tag_id = tags.id
                    ), 0),
                    COALESCE((
                        SELECT MAX(similarity(LOWER(tag_aliases.alias), ?))
                        FROM tag_aliases
                        WHERE tag_aliases.tag_id = tags.id
                    ), 0)
                ) DESC
                ',
                [
                    $normalized,
                    $normalized,
                    $normalized,
                    $like,
                    $like,
                    $like,
                    $normalized,
                    $normalized,
                    $normalized,
                ]
            )
            ->orderedByPopularity()
            ->limit($limit)
            ->get();
    }

    /**
     * Top popular tags for browse selector plus any currently selected tags not in that set.
     *
     * @param  list<int|string>  $selectedIds
     * @return Collection<int, Tag>
     */
    public function forBrowseSelector(array $selectedIds): Collection
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $selectedIds),
            static fn (int $id) => $id > 0
        )));

        $perCategory = (int) config('browse.tag_suggestions.preload_per_category', 7);
        $excludeKeys = config('browse.tag_suggestions.exclude_category_keys_from_preload', []);

        $popular = $this->getModel()->newQuery()->forBrowsePreloadSuggestions($perCategory, $excludeKeys);

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
