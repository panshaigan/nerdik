<?php

namespace App\Support;

use App\Services\TagSelectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BrowseTagFilter
{
    /**
     * Constrain a query to entities linked to tags via {@see $relation} pivot.
     *
     * @param  Builder<Model>  $query
     * @param  list<int>  $tagIds
     */
    public static function apply(Builder $query, array $tagIds, bool $matchAll, string $relation = 'tags'): void
    {
        $selected = array_values(array_unique(array_filter(
            array_map('intval', $tagIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($selected === []) {
            return;
        }

        $expander = app(TagSelectionService::class);

        if ($matchAll) {
            foreach ($selected as $id) {
                $expanded = $expander->expandTagIdsToDescendants([$id]);
                $query->whereHas($relation, fn (Builder $q) => $q->whereIn('tags.id', $expanded));
            }

            return;
        }

        $expanded = $expander->expandTagIdsToDescendants($selected);
        $query->whereHas($relation, fn (Builder $q) => $q->whereIn('tags.id', $expanded));
    }
}
