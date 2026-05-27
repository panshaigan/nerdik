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
                    ->orWhereRaw('similarity(unaccent(LOWER(events.name)), unaccent(LOWER(?))) >= ?', [$normalized, $similarityThreshold])
                    ->orWhereRaw('similarity(unaccent(LOWER(COALESCE(events.description, \'\'))), unaccent(LOWER(?))) >= ?', [$normalized, $similarityThreshold]);
            })
            ->orderByRaw(
                "
                (
                    CASE WHEN unaccent(LOWER(events.name)) = unaccent(LOWER(?)) THEN 3 ELSE 0 END
                    +
                    CASE WHEN unaccent(LOWER(COALESCE(events.description, ''))) = unaccent(LOWER(?)) THEN 3 ELSE 0 END
                    +
                    CASE WHEN events.search_vector @@ {$ftsQuery} THEN 2 ELSE 0 END
                    +
                    CASE WHEN unaccent(LOWER(events.name)) LIKE unaccent(LOWER(?)) OR unaccent(LOWER(COALESCE(events.description, ''))) LIKE unaccent(LOWER(?)) THEN 1 ELSE 0 END
                ) DESC,
                GREATEST(
                    similarity(unaccent(LOWER(events.name)), unaccent(LOWER(?))),
                    similarity(unaccent(LOWER(COALESCE(events.description, ''))), unaccent(LOWER(?)))
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
                    ->orWhereRaw('similarity(unaccent(LOWER(activities.name)), unaccent(LOWER(?))) >= ?', [$normalized, $similarityThreshold])
                    ->orWhereRaw('similarity(unaccent(LOWER(COALESCE(activities.description, \'\'))), unaccent(LOWER(?))) >= ?', [$normalized, $similarityThreshold]);
            })
            ->orderByRaw(
                "
                (
                    CASE WHEN unaccent(LOWER(activities.name)) = unaccent(LOWER(?)) THEN 3 ELSE 0 END
                    +
                    CASE WHEN unaccent(LOWER(COALESCE(activities.description, ''))) = unaccent(LOWER(?)) THEN 3 ELSE 0 END
                    +
                    CASE WHEN activities.search_vector @@ {$ftsQuery} THEN 2 ELSE 0 END
                    +
                    CASE WHEN unaccent(LOWER(activities.name)) LIKE unaccent(LOWER(?)) OR unaccent(LOWER(COALESCE(activities.description, ''))) LIKE unaccent(LOWER(?)) THEN 1 ELSE 0 END
                ) DESC,
                GREATEST(
                    similarity(unaccent(LOWER(activities.name)), unaccent(LOWER(?))),
                    similarity(unaccent(LOWER(COALESCE(activities.description, ''))), unaccent(LOWER(?)))
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
