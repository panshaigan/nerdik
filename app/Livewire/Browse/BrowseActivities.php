<?php

namespace App\Livewire\Browse;

use App\Models\Activity;
use App\Models\Place;
use App\Models\Tag;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseActivities extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $from_date = null;

    #[Url]
    public ?string $to_date = null;

    #[Url]
    public ?int $place_id = null;

    #[Url]
    public ?int $tag_id = null;

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q', 'from_date', 'to_date', 'place_id', 'tag_id']);

        return $this->redirectRoute('activities.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== ''
            || filled($this->from_date)
            || filled($this->to_date)
            || $this->place_id !== null
            || $this->tag_id !== null;
    }

    public function render()
    {
        $query = Activity::with(['host', 'tags.translations', 'slot.event'])
            ->whereHas('slot', fn ($q) => $q->whereHas('event', fn ($e) => $e->where('is_public', true)))
            ->orderBy('updated_at', 'desc');

        if (filled($this->from_date)) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '>=', $this->from_date));
        }
        if (filled($this->to_date)) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '<=', $this->to_date));
        }
        if ($this->place_id !== null) {
            $query->whereHas('slot.places', fn ($q) => $q->where('places.id', $this->place_id));
        }
        if ($this->tag_id !== null) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $this->tag_id));
        }
        if ($this->q !== '') {
            $term = '%'.$this->q.'%';
            $query->where('name', 'like', $term);
        }

        $activities = $query->paginate(12);

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        $wishlistActivityIds = auth()->check()
            ? auth()->user()->wishlistActivities()->pluck('activities.id')->toArray()
            : [];

        return view('livewire.browse.browse-activities', [
            'activities' => $activities,
            'places' => $places,
            'tags' => $tags,
            'wishlistActivityIds' => $wishlistActivityIds,
        ]);
    }
}
