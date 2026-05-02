<?php

namespace App\Livewire\Dashboard;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Dashboard extends Component
{
    use Toast;
    use WithPagination;

    private const PER_PAGE = 15;

    public function toggleEventInterest(int $eventId): void
    {
        $event = Event::query()->whereKey($eventId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $alreadyInterested = $user->interestedEvents()->whereKey($event->id)->exists();
        if ($alreadyInterested) {
            $user->interestedEvents()->detach($event->id);
            $this->warning(__('ui.interests.removed_event'));

            return;
        }

        $user->interestedEvents()->syncWithoutDetaching([$event->id]);
        $this->success(__('ui.interests.added_event'));
    }

    public function toggleActivityInterest(int $activityId): void
    {
        $activity = Activity::query()->whereKey($activityId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $alreadyInterested = $user->interestedActivities()->whereKey($activity->id)->exists();
        if ($alreadyInterested) {
            $user->interestedActivities()->detach($activity->id);
            $this->warning(__('ui.interests.removed_activity'));

            return;
        }

        $user->interestedActivities()->syncWithoutDetaching([$activity->id]);
        $eventId = $activity->slot?->event_id;
        if ($eventId !== null) {
            $user->interestedEvents()->syncWithoutDetaching([(int) $eventId]);
        }
        $this->success(__('ui.interests.added_activity'));
    }

    public function render()
    {
        $user = Auth::user();
        $this->toastFromSessionStatus();

        $hostedActivitiesCount = Activity::query()
            ->where('created_by', $user->id)
            ->count();

        $hostedEventsCount = Event::query()
            ->where('created_by', $user->id)
            ->count();

        $participatedActivitiesCount = ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('is_absent', false)
            ->distinct('activity_id')
            ->count('activity_id');

        $feed = $this->buildUnifiedUpcomingFeed($user->id);

        $eventIds = collect($feed->items())
            ->where('kind', 'event')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activityIds = collect($feed->items())
            ->where('kind', 'activity')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $eventsById = $eventIds === []
            ? collect()
            : Event::query()
                ->with([
                    'organization',
                    'creator',
                    'places.country.translations',
                    'places.city.translations',
                    'slots.activity.activityType',
                    'slots.activityTypes',
                ])
                ->whereIn('id', $eventIds)
                ->get()
                ->keyBy('id');

        $activitiesById = $activityIds === []
            ? collect()
            : Activity::query()
                ->with(['creator', 'activityType', 'tags.translations', 'tags.tagCategory', 'slot.event', 'slot.place', 'place'])
                ->withCount(['participants as participants_count' => fn ($q) => $q->where('is_absent', false)])
                ->whereIn('id', $activityIds)
                ->get()
                ->keyBy('id');

        $feedItems = collect($feed->items())
            ->map(function (array $row) use ($eventsById, $activitiesById) {
                if ($row['kind'] === 'event') {
                    $event = $eventsById->get($row['id']);

                    return $event ? ['kind' => 'event', 'event' => $event] : null;
                }

                $activity = $activitiesById->get($row['id']);

                return $activity ? ['kind' => 'activity', 'activity' => $activity] : null;
            })
            ->filter()
            ->values();

        $feed->setCollection($feedItems);

        $interestedEventIds = $user->interestedEvents()->pluck('events.id')->map(fn ($id) => (int) $id)->all();
        $interestedActivityIds = $user->interestedActivities()->pluck('activities.id')->map(fn ($id) => (int) $id)->all();
        $participatingActivityIds = ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('is_absent', false)
            ->distinct('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $participatingEventIds = DB::table('activity_user')
            ->join('slots', 'slots.activity_id', '=', 'activity_user.activity_id')
            ->whereNotNull('slots.event_id')
            ->where('activity_user.user_id', $user->id)
            ->where('activity_user.is_absent', false)
            ->distinct()
            ->pluck('slots.event_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.dashboard.dashboard', [
            'hostedActivitiesCount' => $hostedActivitiesCount,
            'hostedEventsCount' => $hostedEventsCount,
            'participatedActivitiesCount' => $participatedActivitiesCount,
            'feed' => $feed,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
            'participatingEventIds' => $participatingEventIds,
        ]);
    }

    private function buildUnifiedUpcomingFeed(int $userId): LengthAwarePaginator
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
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId)->where('is_absent', false))
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
        $rows = $rows->concat($interestedActivityRows);

        $deduped = $rows
            ->reduce(function ($carry, array $row) {
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

        $sorted = collect(array_values($deduped))
            ->sortBy(fn (array $row) => $row['sort_at'])
            ->values();

        $page = (int) request()->query('page', 1);
        $total = $sorted->count();
        $slice = $sorted->forPage($page, self::PER_PAGE)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
