<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CatalogQuery
{
    /**
     * @return Builder<Place>
     */
    public static function places(string $q = ''): Builder
    {
        $query = Place::query()
            ->venues()
            ->with(['city', 'country'])
            ->orderBy('places.name')
            ->orderBy('places.id');

        self::applyUnaccentSearch($query, $q, ['places.name', 'places.address']);

        return $query;
    }

    /**
     * @return Builder<Organization>
     */
    public static function organizations(string $q = ''): Builder
    {
        $query = Organization::query()
            ->orderBy('organizations.name')
            ->orderBy('organizations.id');

        self::applyUnaccentSearch($query, $q, ['organizations.name', 'organizations.acronym']);

        return $query;
    }

    /**
     * @return Builder<EventSeries>
     */
    public static function series(string $q = ''): Builder
    {
        $query = EventSeries::query()
            ->whereHas('events', function (Builder $events): void {
                $events->where('events.is_public', true)
                    ->whereNull('events.cancelled_at');
            })
            ->withCount([
                'events as upcoming_public_events_count' => function (Builder $events): void {
                    $events->where('events.is_public', true)
                        ->whereNull('events.cancelled_at')
                        ->whereNotNull('events.starts_at')
                        ->where('events.starts_at', '>=', now());
                },
            ])
            ->orderBy('event_series.name')
            ->orderBy('event_series.id');

        self::applyUnaccentSearch($query, $q, ['event_series.name']);

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $qualifiedColumns
     */
    private static function applyUnaccentSearch(Builder $query, string $term, array $qualifiedColumns): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $normalized = mb_strtolower($term);
        $like = '%'.$normalized.'%';

        $query->where(function (Builder $outer) use ($qualifiedColumns, $like): void {
            foreach ($qualifiedColumns as $index => $column) {
                $sql = "unaccent(LOWER(COALESCE({$column}, ''))) LIKE unaccent(LOWER(?))";
                if ($index === 0) {
                    $outer->whereRaw($sql, [$like]);
                } else {
                    $outer->orWhereRaw($sql, [$like]);
                }
            }
        });
    }
}
