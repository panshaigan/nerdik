<?php

namespace App\Livewire\Browse;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Livewire\Concerns\WithBrowseTagFilter;
use App\Models\Activity;
use App\Models\Place;
use App\Models\Tag;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseActivities extends Component
{
    use WithBrowseListingSort;
    use WithBrowseTagFilter;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $from_date = null;

    #[Url]
    public ?string $to_date = null;

    #[Url]
    public ?int $place_id = null;

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function clearTextSearch(): void
    {
        $this->q = '';
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function updatedPlaceId(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q', 'from_date', 'to_date', 'place_id']);
        $this->resetTagFilter();

        return $this->redirectRoute('search.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== ''
            || filled($this->from_date)
            || filled($this->to_date)
            || $this->place_id !== null
            || $this->hasTagFilterActive();
    }

    public function render()
    {
        $query = Activity::with(['creator', 'tags.translations', 'slot.event'])
            ->attachedToPublicEvent();

        if (filled($this->from_date)) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '>=', $this->from_date));
        }
        if (filled($this->to_date)) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '<=', $this->to_date));
        }
        if ($this->place_id !== null) {
            $query->whereHas('slot.places', fn ($q) => $q->where('places.id', $this->place_id));
        }

        $this->applyBrowseTagFilter($query, 'tags');

        if ($this->q !== '') {
            $term = '%'.$this->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }

        $this->applyBrowseActivitySort($query);

        $activities = $query->paginate(self::PER_PAGE);

        $places = Place::orderBy('name')->get();

        $wishlistActivityIds = auth()->check()
            ? auth()->user()->wishlistActivities()->pluck('activities.id')->toArray()
            : [];

        return view('livewire.browse.browse-activities', [
            'activities' => $activities,
            'places' => $places,
            'wishlistActivityIds' => $wishlistActivityIds,
            'tags' => Tag::orderedForSelector()->get(),
        ]);
    }
}
