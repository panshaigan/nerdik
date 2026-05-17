<?php

declare(strict_types=1);

namespace App\Support\Browse;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class BrowseFullTextSearch
{
    /**
     * @param  Builder<Model>  $query
     */
    public static function apply(Builder $query, string $term, string $qualifiedColumn): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $query->whereRaw("{$qualifiedColumn} @@ plainto_tsquery('polish', ?)", [$term]);
    }
}
