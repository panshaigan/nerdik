<?php

namespace App\Livewire\Browse;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Livewire\Concerns\WithBrowseTagFilter;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseEvents extends Component
{
    use WithBrowseListingSort;
    use WithBrowseTagFilter;
    use WithPagination;

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
        $this->reset(['q', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'include_past_events']);
        $this->resetTagFilter();

        return $this->redirectRoute('events.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== ''
            || $this->hasTagFilterActive()
            || filled($this->min_lat)
            || filled($this->max_lat)
            || filled($this->min_lng)
            || filled($this->max_lng);
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

    /**
     * @return Builder<Event>
     */
    protected function baseEventQuery(): Builder
    {
        $query = Event::query()->where('is_public', true);

        if (! $this->include_past_events) {
            $query->whereRaw('COALESCE(events.ends_at, events.starts_at) >= ?', [now()]);
        }

        if ($this->q !== '') {
            $term = '%'.$this->q.'%';
            $query->where(fn (Builder $q) => $q->where('events.name', 'like', $term)->orWhere('events.desc', 'like', $term));
        }

        $this->applyBrowseTagFilter($query, 'tags');

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
            $term = '%'.$this->q.'%';
            $query->where(fn (Builder $q) => $q->where('activities.name', 'like', $term)->orWhere('activities.desc', 'like', $term));
        }

        $this->applyBrowseTagFilter($query, 'tags');

        if ($this->hasBBox()) {
            [$minLat, $maxLat, $minLng, $maxLng] = $this->normalizedBBox();
            $query->whereHas('slot.places', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
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

    public function render()
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
            DB::raw('(SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1) as sort_at'),
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

        $paginator = $outer->paginate(12);

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
                    'tags.translations',
                    'places.country.translations',
                    'places.city.translations',
                ])
                ->whereIn('id', $eventIds)
                ->get()
                ->keyBy('id');

        $activities = $activityIds === []
            ? collect()
            : Activity::query()
                ->with(['creator', 'tags.translations', 'slot.event'])
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

        $wishlistEventIds = auth()->check()
            ? auth()->user()->wishlistEvents()->pluck('events.id')->toArray()
            : [];

        $wishlistActivityIds = auth()->check()
            ? auth()->user()->wishlistActivities()->pluck('activities.id')->toArray()
            : [];

        return view('livewire.browse.browse-events', [
            'browseListings' => $paginator,
            'wishlistEventIds' => $wishlistEventIds,
            'wishlistActivityIds' => $wishlistActivityIds,
            'tags' => Tag::with(['translations', 'aliases', 'tagAttachments'])->orderBy('category')->orderBy('slug')->get(),
        ]);
    }
}
