<?php

namespace App\Livewire\Browse;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Livewire\Concerns\WithBrowseTagFilter;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class BrowseEvents extends Component
{
    use Toast;
    use WithBrowseListingSort;
    use WithBrowseTagFilter;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $min_lat = null;

    #[Url]
    public ?string $max_lat = null;

    #[Url]
    public ?string $min_lng = null;

    #[Url]
    public ?string $max_lng = null;

    /** When false (default), only events that have not ended yet (see COALESCE(ends_at, starts_at)). */
    #[Url]
    public bool $include_past_events = false;

    /** Mutually exclusive with {@see $only_activities}; both false = search events and activities. */
    #[Url]
    public bool $only_events = false;

    /** Mutually exclusive with {@see $only_events}. */
    #[Url]
    public bool $only_activities = false;

    public function updatedOnlyEvents(bool $value): void
    {
        if ($value) {
            $this->only_activities = false;
        }
        $this->resetPage();
    }

    public function updatedOnlyActivities(bool $value): void
    {
        if ($value) {
            $this->only_events = false;
        }
        $this->resetPage();
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function clearTextSearch(): void
    {
        $this->q = '';
    }

    public function updatedMinLat(): void
    {
        $this->resetPage();
    }

    public function updatedMaxLat(): void
    {
        $this->resetPage();
    }

    public function updatedMinLng(): void
    {
        $this->resetPage();
    }

    public function updatedMaxLng(): void
    {
        $this->resetPage();
    }

    public function updatedIncludePastEvents(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'include_past_events', 'only_events', 'only_activities']);
        $this->resetTagFilter();

        return $this->redirectRoute('search.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== ''
            || $this->hasTagFilterActive()
            || filled($this->min_lat)
            || filled($this->max_lat)
            || filled($this->min_lng)
            || filled($this->max_lng)
            || $this->only_events
            || $this->only_activities;
    }

    public function hasBBox(): bool
    {
        if (! filled($this->min_lat) || ! filled($this->max_lat)
            || ! filled($this->min_lng) || ! filled($this->max_lng)) {
            return false;
        }

        foreach ([$this->min_lat, $this->max_lat, $this->min_lng, $this->max_lng] as $v) {
            if (! is_numeric($v)) {
                return false;
            }
        }

        $minLat = (float) $this->min_lat;
        $maxLat = (float) $this->max_lat;
        $minLng = (float) $this->min_lng;
        $maxLng = (float) $this->max_lng;

        if (! is_finite($minLat) || ! is_finite($maxLat) || ! is_finite($minLng) || ! is_finite($maxLng)) {
            return false;
        }

        if ($minLat < -90.0 || $maxLat > 90.0 || $minLng < -180.0 || $maxLng > 180.0) {
            return false;
        }

        return true;
    }

    public function toggleEventInterest(int $eventId): void
    {
        $event = Event::query()->whereKey($eventId)->firstOrFail();
        $user = auth()->user();
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
        $user = auth()->user();
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

    /**
     * @return Builder<Event>
     */
    protected function baseEventQuery(): Builder
    {
        $query = Event::query()
            ->where('is_public', true)
            ->whereNull('events.cancelled_at');

        if (! $this->include_past_events) {
            $query->whereRaw('COALESCE(events.ends_at, events.starts_at) >= ?', [now()]);
        }

        if ($this->q !== '') {
            $term = '%'.mb_strtolower($this->q).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(events.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(events.description) LIKE ?', [$term]));
        }

        $this->applyBrowseTagFilter($query, 'slots.activity.tags');

        if ($this->hasBBox()) {
            [$minLat, $maxLat, $minLng, $maxLng] = $this->normalizedBBox();
            $query->whereHas('places', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
            });
        }

        return $query;
    }

    /**
     * Activities scheduled on a public event (slot with event_id); optional “not ended yet” on that slot.
     *
     * @return Builder<Activity>
     */
    protected function baseActivityQuery(): Builder
    {
        $query = Activity::query()->attachedToPublicEvent(! $this->include_past_events);

        if ($this->q !== '') {
            $term = '%'.mb_strtolower($this->q).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(activities.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(activities.description) LIKE ?', [$term]));
        }

        $this->applyBrowseTagFilter($query, 'tags');

        if ($this->hasBBox()) {
            [$minLat, $maxLat, $minLng, $maxLng] = $this->normalizedBBox();
            $query->where(function (Builder $outer) use ($minLat, $maxLat, $minLng, $maxLng): void {
                $outer->where(function (Builder $selfHosted) use ($minLat, $maxLat, $minLng, $maxLng): void {
                    $selfHosted->where('activities.hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                        ->whereHas('place', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng): void {
                            $q->whereNotNull('latitude')
                                ->whereNotNull('longitude')
                                ->whereBetween('latitude', [$minLat, $maxLat])
                                ->whereBetween('longitude', [$minLng, $maxLng]);
                        });
                })->orWhere(function (Builder $scheduled) use ($minLat, $maxLat, $minLng, $maxLng): void {
                    $scheduled->where('activities.hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                        ->whereHas('slot.place', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng): void {
                            $q->whereNotNull('latitude')
                                ->whereNotNull('longitude')
                                ->whereBetween('latitude', [$minLat, $maxLat])
                                ->whereBetween('longitude', [$minLng, $maxLng]);
                        });
                });
            });
        }

        return $query;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function normalizedBBox(): array
    {
        $minLat = (float) $this->min_lat;
        $maxLat = (float) $this->max_lat;
        $minLng = (float) $this->min_lng;
        $maxLng = (float) $this->max_lng;
        if ($minLat > $maxLat) {
            [$minLat, $maxLat] = [$maxLat, $minLat];
        }
        if ($minLng > $maxLng) {
            [$minLng, $maxLng] = [$maxLng, $minLng];
        }

        return [$minLat, $maxLat, $minLng, $maxLng];
    }

    /**
     * @return LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}>
     */
    protected function paginateBrowseListings()
    {
        if ($this->only_events && ! $this->only_activities) {
            return $this->paginateEventsOnly();
        }

        if ($this->only_activities && ! $this->only_events) {
            return $this->paginateActivitiesOnly();
        }

        return $this->paginateEventsAndActivitiesUnion();
    }

    /**
     * @return LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}>
     */
    protected function paginateEventsOnly()
    {
        $query = $this->baseEventQuery()->with([
            'organization',
            'creator',
            'places.country.translations',
            'places.city.translations',
            'slots.activity.activityType',
            'slots.activityTypes',
        ]);
        $this->applyBrowseEventSort($query);
        $paginator = $query->paginate(self::PER_PAGE);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Event $event) => ['kind' => 'event', 'event' => $event])->values()
        );

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}>
     */
    protected function paginateActivitiesOnly()
    {
        $query = $this->baseActivityQuery()->with(['creator', 'activityType', 'tags.translations', 'tags.tagCategory', 'slot.event', 'slot.place', 'place'])
            ->withCount(['participants as participants_count' => fn (Builder $q) => $q->where('is_absent', false)]);
        $this->applyBrowseActivitySort($query);
        $paginator = $query->paginate(self::PER_PAGE);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Activity $activity) => ['kind' => 'activity', 'activity' => $activity])->values()
        );

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, array{kind: string, event?: Event, activity?: Activity}>
     */
    protected function paginateEventsAndActivitiesUnion()
    {
        $eventPart = $this->baseEventQuery()->select([
            DB::raw("'event' as listing_kind"),
            'events.id as listing_id',
            'events.name as sort_name',
            DB::raw('COALESCE(events.ends_at, events.starts_at) as sort_at'),
        ]);

        $activityPart = $this->baseActivityQuery()->select([
            DB::raw("'activity' as listing_kind"),
            'activities.id as listing_id',
            'activities.name as sort_name',
            DB::raw('COALESCE((SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1), COALESCE(activities.ends_at, activities.starts_at)) as sort_at'),
        ]);

        $union = $eventPart->toBase()->unionAll($activityPart->toBase());

        $outer = DB::query()->fromSub($union, 'merged');
        $dir = $this->browseSortDirection();
        if ($this->browseSortKey() === 'name') {
            $outer->orderBy('sort_name', $dir);
        } else {
            $outer->orderBy('sort_at', $dir);
        }
        $outer->orderBy('listing_kind')->orderBy('listing_id');

        $paginator = $outer->paginate(self::PER_PAGE);

        $eventIds = [];
        $activityIds = [];
        foreach ($paginator->items() as $row) {
            if ($row->listing_kind === 'event') {
                $eventIds[] = (int) $row->listing_id;
            } else {
                $activityIds[] = (int) $row->listing_id;
            }
        }

        $events = $eventIds === []
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

        $activities = $activityIds === []
            ? collect()
            : Activity::query()
                ->with(['creator', 'activityType', 'tags.translations', 'tags.tagCategory', 'slot.event', 'slot.place', 'place'])
                ->withCount(['participants as participants_count' => fn (Builder $q) => $q->where('is_absent', false)])
                ->whereIn('id', $activityIds)
                ->get()
                ->keyBy('id');

        $ordered = collect($paginator->items())->map(function ($row) use ($events, $activities) {
            if ($row->listing_kind === 'event') {
                $event = $events->get((int) $row->listing_id);

                return $event ? ['kind' => 'event', 'event' => $event] : null;
            }

            $activity = $activities->get((int) $row->listing_id);

            return $activity ? ['kind' => 'activity', 'activity' => $activity] : null;
        })->filter()->values();

        $paginator->setCollection($ordered);

        return $paginator;
    }

    public function render()
    {
        $paginator = $this->paginateBrowseListings();

        $interestedEventIds = auth()->check()
            ? auth()->user()->interestedEvents()->pluck('events.id')->toArray()
            : [];

        $interestedActivityIds = auth()->check()
            ? auth()->user()->interestedActivities()->pluck('activities.id')->toArray()
            : [];
        $participatingActivityIds = auth()->check()
            ? ActivityUser::query()
                ->where('user_id', auth()->id())
                ->where('is_absent', false)
                ->distinct('activity_id')
                ->pluck('activity_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $participatingEventIds = auth()->check()
            ? DB::table('activity_user')
                ->join('slots', 'slots.activity_id', '=', 'activity_user.activity_id')
                ->whereNotNull('slots.event_id')
                ->where('activity_user.user_id', auth()->id())
                ->where('activity_user.is_absent', false)
                ->distinct()
                ->pluck('slots.event_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        return view('livewire.browse.browse-events', [
            'browseListings' => $paginator,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
            'participatingEventIds' => $participatingEventIds,
            'tags' => Tag::orderedForSelector()->get(),
        ]);
    }
}
