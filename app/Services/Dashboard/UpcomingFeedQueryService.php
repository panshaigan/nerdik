<?php

namespace App\Services\Dashboard;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpcomingFeedQueryService
{
    /**
     * @return Collection<int, array{kind: string, id: int, sort_at: mixed}>
     */
    public function buildUnifiedUpcomingRows(int $userId): Collection
    {
        $eventSortExpr = 'COALESCE(events.ends_at, events.starts_at)';
        $activitySortExpr = 'COALESCE((SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id ORDER BY slots.id ASC LIMIT 1), COALESCE(activities.ends_at, activities.starts_at))';

        $rows = collect();

        $myEventRows = Event::query()
            ->where('created_by', $userId)
            ->whereRaw($eventSortExpr.' >= ?', [now()])
            ->select(['id'])
            ->selectRaw($eventSortExpr.' as sort_at')
            ->get()
            ->map(fn (Event $event) => ['kind' => 'event', 'id' => (int) $event->id, 'sort_at' => $event->sort_at]);
        $rows = $rows->concat($myEventRows);

        $myActivityRows = Activity::query()
            ->where('created_by', $userId)
            ->whereRaw($activitySortExpr.' >= ?', [now()])
            ->select(['id'])
            ->selectRaw($activitySortExpr.' as sort_at')
            ->get()
            ->map(fn (Activity $activity) => ['kind' => 'activity', 'id' => (int) $activity->id, 'sort_at' => $activity->sort_at]);
        $rows = $rows->concat($myActivityRows);

        $participatingActivityRows = Activity::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)->where('is_absent', false))
            ->whereRaw($activitySortExpr.' >= ?', [now()])
            ->select(['id'])
            ->selectRaw($activitySortExpr.' as sort_at')
            ->get()
            ->map(fn (Activity $activity) => ['kind' => 'activity', 'id' => (int) $activity->id, 'sort_at' => $activity->sort_at]);
        $rows = $rows->concat($participatingActivityRows);

        $interestedEventRows = DB::table('user_interests')
            ->join('events', 'events.id', '=', 'user_interests.interest_id')
            ->where('user_interests.user_id', $userId)
            ->where('user_interests.interest_type', (new Event)->getMorphClass())
            ->whereRaw($eventSortExpr.' >= ?', [now()])
            ->selectRaw("'event' as kind, events.id as id, ".$eventSortExpr.' as sort_at')
            ->get()
            ->map(fn ($row) => ['kind' => 'event', 'id' => (int) $row->id, 'sort_at' => $row->sort_at]);
        $rows = $rows->concat($interestedEventRows);

        $interestedActivityRows = DB::table('user_interests')
            ->join('activities', 'activities.id', '=', 'user_interests.interest_id')
            ->where('user_interests.user_id', $userId)
            ->where('user_interests.interest_type', (new Activity)->getMorphClass())
            ->whereRaw($activitySortExpr.' >= ?', [now()])
            ->selectRaw("'activity' as kind, activities.id as id, ".$activitySortExpr.' as sort_at')
            ->get()
            ->map(fn ($row) => ['kind' => 'activity', 'id' => (int) $row->id, 'sort_at' => $row->sort_at]);

        return $rows->concat($interestedActivityRows);
    }

    /**
     * @param  Collection<int, array{kind: string, id: int, sort_at: mixed}>  $rows
     * @return Collection<int, array{kind: string, id: int, sort_at: mixed}>
     */
    public function dedupeAndSort(Collection $rows): Collection
    {
        /** @var array<string, array{kind: string, id: int, sort_at: mixed}> $deduped */
        $deduped = $rows->reduce(function (array $carry, array $row): array {
            $key = $row['kind'].':'.$row['id'];
            if (! isset($carry[$key])) {
                $carry[$key] = $row;

                return $carry;
            }

            $existing = $carry[$key]['sort_at'];
            if ($existing === null || ($row['sort_at'] !== null && $row['sort_at'] < $existing)) {
                $carry[$key] = $row;
            }

            return $carry;
        }, []);

        return collect(array_values($deduped))
            ->sortBy(fn (array $row) => $row['sort_at'])
            ->values();
    }
}
