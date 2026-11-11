<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Activity;
use App\Services\ActivityPeopleStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class CatalogSeriesSortStats
{
    public const CACHE_KEY = 'catalog.series.sort_stats.v1';

    /** @var list<int> */
    public const FLEXIBLE_TTL = [300, 900];

    public function __construct(
        private ActivityPeopleStats $activityPeopleStats,
    ) {}

    /**
     * @return array{
     *     activity: array<int, array{activities: int, participants: int}>,
     *     event: array<int, array{activities: int, participants: int}>,
     * }
     */
    public function all(): array
    {
        /** @var array{activity: array<int, array{activities: int, participants: int}>, event: array<int, array{activities: int, participants: int}>} $stats */
        $stats = Cache::flexible(self::CACHE_KEY, self::FLEXIBLE_TTL, fn (): array => $this->compute());

        return $stats;
    }

    /**
     * @return array{activities: int, participants: int}
     */
    public function forListing(string $kind, int $seriesId): array
    {
        $bucket = $kind === 'activity' ? 'activity' : 'event';
        $stats = $this->all()[$bucket][$seriesId] ?? null;

        if ($stats === null) {
            return ['activities' => 0, 'participants' => 0];
        }

        return $stats;
    }

    /**
     * @return array{
     *     activity: array<int, array{activities: int, participants: int}>,
     *     event: array<int, array{activities: int, participants: int}>,
     * }
     */
    private function compute(): array
    {
        return [
            'activity' => $this->activitySeriesMaps(),
            'event' => $this->eventSeriesMaps(),
        ];
    }

    /**
     * @return array<int, array{activities: int, participants: int}>
     */
    private function activitySeriesMaps(): array
    {
        /** @var Collection<int, Collection<int, Activity>> $bySeriesId */
        $bySeriesId = Activity::query()
            ->attachedToPublicEvent()
            ->whereNotNull('activity_series_id')
            ->get(['id', 'activity_series_id'])
            ->groupBy(fn (Activity $activity): int => (int) $activity->activity_series_id);

        $result = [];
        foreach ($bySeriesId as $seriesId => $activities) {
            $ids = $activities->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $result[(int) $seriesId] = [
                'activities' => count($ids),
                'participants' => $this->activityPeopleStats->uniqueIncludingHosts($ids, excludeAbsent: true),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{activities: int, participants: int}>
     */
    private function eventSeriesMaps(): array
    {
        $rows = Activity::query()
            ->attachedToPublicEvent()
            ->join('slots', 'slots.activity_id', '=', 'activities.id')
            ->join('events', 'events.id', '=', 'slots.event_id')
            ->whereNotNull('slots.event_id')
            ->where('events.is_public', true)
            ->whereNull('events.cancelled_at')
            ->whereNotNull('events.event_series_id')
            ->select([
                'activities.id as activity_id',
                'events.event_series_id as event_series_id',
            ])
            ->distinct()
            ->get();

        /** @var Collection<int, Collection<int, int>> $bySeriesId */
        $bySeriesId = $rows->groupBy(fn (object $row): int => (int) $row->event_series_id)
            ->map(fn (Collection $group) => $group->pluck('activity_id')->map(fn (mixed $id): int => (int) $id)->unique()->values());

        $result = [];
        foreach ($bySeriesId as $seriesId => $activityIds) {
            $ids = $activityIds->all();
            $result[(int) $seriesId] = [
                'activities' => count($ids),
                'participants' => $this->activityPeopleStats->uniqueIncludingHosts($ids, excludeAbsent: true),
            ];
        }

        return $result;
    }
}
