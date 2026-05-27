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

    /**
     * @param  Builder<Model>  $query
     */
    public static function applyEventHybrid(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $normalized = mb_strtolower($term);
        $like = '%'.$normalized.'%';
        $similarityThreshold = 0.15;
        $ftsQuery = 'plainto_tsquery(\'polish\', ?)';

        $query
            ->where(function (Builder $outer) use ($term, $normalized, $similarityThreshold, $ftsQuery): void {
                $outer->whereRaw("events.search_vector @@ {$ftsQuery}", [$term])
                    ->orWhereRaw('similarity(LOWER(events.name), ?) >= ?', [$normalized, $similarityThreshold])
                    ->orWhereRaw('similarity(LOWER(COALESCE(events.description, \'\')), ?) >= ?', [$normalized, $similarityThreshold]);
            })
            ->orderByRaw(
                "
                (
                    CASE WHEN LOWER(events.name) = ? THEN 3 ELSE 0 END
                    +
                    CASE WHEN LOWER(COALESCE(events.description, '')) = ? THEN 3 ELSE 0 END
                    +
                    CASE WHEN events.search_vector @@ {$ftsQuery} THEN 2 ELSE 0 END
                    +
                    CASE WHEN LOWER(events.name) LIKE ? OR LOWER(COALESCE(events.description, '')) LIKE ? THEN 1 ELSE 0 END
                ) DESC,
                GREATEST(
                    similarity(LOWER(events.name), ?),
                    similarity(LOWER(COALESCE(events.description, '')), ?)
                ) DESC
                ",
                [
                    $normalized,
                    $normalized,
                    $term,
                    $like,
                    $like,
                    $normalized,
                    $normalized,
                ]
            );
    }

    /**
     * @param  Builder<Model>  $query
     */
    public static function applyActivityHybrid(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $normalized = mb_strtolower($term);
        $like = '%'.$normalized.'%';
        $similarityThreshold = 0.15;
        $ftsQuery = 'plainto_tsquery(\'polish\', ?)';

        $query
            ->where(function (Builder $outer) use ($term, $normalized, $similarityThreshold, $ftsQuery): void {
                $outer->whereRaw("activities.search_vector @@ {$ftsQuery}", [$term])
                    ->orWhereRaw('similarity(LOWER(activities.name), ?) >= ?', [$normalized, $similarityThreshold])
                    ->orWhereRaw('similarity(LOWER(COALESCE(activities.description, \'\')), ?) >= ?', [$normalized, $similarityThreshold]);
            })
            ->orderByRaw(
                "
                (
                    CASE WHEN LOWER(activities.name) = ? THEN 3 ELSE 0 END
                    +
                    CASE WHEN LOWER(COALESCE(activities.description, '')) = ? THEN 3 ELSE 0 END
                    +
                    CASE WHEN activities.search_vector @@ {$ftsQuery} THEN 2 ELSE 0 END
                    +
                    CASE WHEN LOWER(activities.name) LIKE ? OR LOWER(COALESCE(activities.description, '')) LIKE ? THEN 1 ELSE 0 END
                ) DESC,
                GREATEST(
                    similarity(LOWER(activities.name), ?),
                    similarity(LOWER(COALESCE(activities.description, '')), ?)
                ) DESC
                ",
                [
                    $normalized,
                    $normalized,
                    $term,
                    $like,
                    $like,
                    $normalized,
                    $normalized,
                ]
            );
    }
}
