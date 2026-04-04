<?php

namespace App\Support;

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
        $ids = array_values(array_unique(array_filter(array_map('intval', $tagIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return;
        }

        if ($matchAll) {
            foreach ($ids as $id) {
                $query->whereHas($relation, fn (Builder $q) => $q->where('tags.id', $id));
            }

            return;
        }

        $query->whereHas($relation, fn (Builder $q) => $q->whereIn('tags.id', $ids));
    }
}
