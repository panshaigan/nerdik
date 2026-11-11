<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CatalogQuery
{
    /**
     * @return Builder<Place>
     */
    public static function places(string $q = ''): Builder
    {
        $query = Place::query()
            ->venues()
            ->with(['city', 'country', 'links'])
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
        return self::visibleEventSeries($q)
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
    }

    /**
     * @return Builder<ActivitySeries>
     */
    public static function activitySeries(string $q = ''): Builder
    {
        $startsAtSql = Activity::scheduleStartsAtSql();

        return self::visibleActivitySeries($q)
            ->withCount([
                'activities as upcoming_public_activities_count' => function (Builder $activities) use ($startsAtSql): void {
                    $activities->attachedToPublicEvent()
                        ->whereRaw("{$startsAtSql} IS NOT NULL")
                        ->whereRaw("{$startsAtSql} >= ?", [now()]);
                },
            ])
            ->orderBy('activity_series.name')
            ->orderBy('activity_series.id');
    }

    /**
     * @return LengthAwarePaginator<int, array{kind: 'event'|'activity', eventSeries?: EventSeries, activitySeries?: ActivitySeries}>
     */
    public static function paginatedSeries(
        string $q = '',
        int $perPage = 21,
        int $page = 1,
        string $sort = 'popularity',
        string $sortDir = 'desc',
    ): LengthAwarePaginator {
        $sort = in_array($sort, ['popularity', 'activities', 'name'], true) ? $sort : 'popularity';
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $eventPart = self::visibleEventSeries($q)->select([
            DB::raw("'event' as listing_kind"),
            'event_series.id as listing_id',
            'event_series.name as sort_name',
        ]);

        $activityPart = self::visibleActivitySeries($q)->select([
            DB::raw("'activity' as listing_kind"),
            'activity_series.id as listing_id',
            'activity_series.name as sort_name',
        ]);

        /** @var Collection<int, object{listing_kind: string, listing_id: int|string, sort_name: string}> $rows */
        $rows = collect(
            DB::query()
                ->fromSub($eventPart->toBase()->unionAll($activityPart->toBase()), 'merged')
                ->get()
        );

        $sortStats = app(CatalogSeriesSortStats::class)->all();

        $sorted = $rows
            ->sort(fn (object $left, object $right): int => self::compareSeriesCatalogRows($left, $right, $sort, $sortDir, $sortStats))
            ->values();

        $total = $sorted->count();
        $pageRows = $sorted->forPage($page, $perPage)->values();

        $eventIds = [];
        $activityIds = [];
        foreach ($pageRows as $row) {
            if ($row->listing_kind === 'event') {
                $eventIds[] = (int) $row->listing_id;
            } else {
                $activityIds[] = (int) $row->listing_id;
            }
        }

        $eventSeries = $eventIds === []
            ? collect()
            : self::series($q)
                ->whereIn('event_series.id', $eventIds)
                ->get()
                ->keyBy('id');

        $activitySeries = $activityIds === []
            ? collect()
            : self::activitySeries($q)
                ->whereIn('activity_series.id', $activityIds)
                ->get()
                ->keyBy('id');

        $items = $pageRows->map(function (object $row) use ($eventSeries, $activitySeries): ?array {
            if ($row->listing_kind === 'event') {
                $series = $eventSeries->get((int) $row->listing_id);

                return $series instanceof EventSeries
                    ? ['kind' => 'event', 'eventSeries' => $series]
                    : null;
            }

            $series = $activitySeries->get((int) $row->listing_id);

            return $series instanceof ActivitySeries
                ? ['kind' => 'activity', 'activitySeries' => $series]
                : null;
        })->filter()->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array{
     *     activity: array<int, array{activities: int, participants: int}>,
     *     event: array<int, array{activities: int, participants: int}>,
     * }  $sortStats
     */
    private static function compareSeriesCatalogRows(
        object $left,
        object $right,
        string $sort,
        string $sortDir,
        array $sortStats,
    ): int {
        $primary = match ($sort) {
            'name' => self::compareSeriesCatalogNames((string) $left->sort_name, (string) $right->sort_name),
            'activities' => self::compareSeriesCatalogCounts(
                self::seriesSortActivities($left, $sortStats),
                self::seriesSortActivities($right, $sortStats),
            ),
            default => self::compareSeriesCatalogCounts(
                self::seriesSortParticipants($left, $sortStats),
                self::seriesSortParticipants($right, $sortStats),
            ),
        };

        if ($primary !== 0) {
            return $sortDir === 'asc' ? $primary : -$primary;
        }

        $nameCompare = self::compareSeriesCatalogNames((string) $left->sort_name, (string) $right->sort_name);
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        $kindCompare = strcmp((string) $left->listing_kind, (string) $right->listing_kind);
        if ($kindCompare !== 0) {
            return $kindCompare;
        }

        return ((int) $left->listing_id) <=> ((int) $right->listing_id);
    }

    /**
     * @param  array{
     *     activity: array<int, array{activities: int, participants: int}>,
     *     event: array<int, array{activities: int, participants: int}>,
     * }  $sortStats
     */
    private static function seriesSortParticipants(object $row, array $sortStats): int
    {
        $bucket = $row->listing_kind === 'activity' ? 'activity' : 'event';

        return $sortStats[$bucket][(int) $row->listing_id]['participants'] ?? 0;
    }

    /**
     * @param  array{
     *     activity: array<int, array{activities: int, participants: int}>,
     *     event: array<int, array{activities: int, participants: int}>,
     * }  $sortStats
     */
    private static function seriesSortActivities(object $row, array $sortStats): int
    {
        $bucket = $row->listing_kind === 'activity' ? 'activity' : 'event';

        return $sortStats[$bucket][(int) $row->listing_id]['activities'] ?? 0;
    }

    private static function compareSeriesCatalogNames(string $left, string $right): int
    {
        return strnatcasecmp($left, $right);
    }

    private static function compareSeriesCatalogCounts(int $left, int $right): int
    {
        return $left <=> $right;
    }

    /**
     * @return Builder<EventSeries>
     */
    private static function visibleEventSeries(string $q = ''): Builder
    {
        $query = EventSeries::query()
            ->whereHas('events', function (Builder $events): void {
                $events->where('events.is_public', true)
                    ->whereNull('events.cancelled_at');
            });

        self::applyUnaccentSearch($query, $q, ['event_series.name']);

        return $query;
    }

    /**
     * @return Builder<ActivitySeries>
     */
    private static function visibleActivitySeries(string $q = ''): Builder
    {
        $query = ActivitySeries::query()
            ->whereHas('activities', function (Builder $activities): void {
                $activities->attachedToPublicEvent();
            });

        self::applyUnaccentSearch($query, $q, ['activity_series.name']);

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
