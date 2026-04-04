<?php

namespace App\Livewire\Browse;

use App\Livewire\Concerns\WithBrowseTagFilter;
use App\Models\Event;
use App\Models\Tag;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseEvents extends Component
{
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

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q', 'min_lat', 'max_lat', 'min_lng', 'max_lng']);
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
        return filled($this->min_lat) && filled($this->max_lat)
            && filled($this->min_lng) && filled($this->max_lng);
    }

    public function render()
    {
        $query = Event::with([
            'organization',
            'creator',
            'tags.translations',
            'places.country.translations',
            'places.city.translations',
        ])
            ->where('is_public', true)
            ->orderBy('starts_at', 'desc');

        if ($this->q !== '') {
            $term = '%'.$this->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }

        $this->applyBrowseTagFilter($query, 'tags');

        if ($this->hasBBox()) {
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
            $query->whereHas('places', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
            });
        }

        $events = $query->paginate(12);

        $wishlistEventIds = auth()->check()
            ? auth()->user()->wishlistEvents()->pluck('events.id')->toArray()
            : [];

        return view('livewire.browse.browse-events', [
            'events' => $events,
            'wishlistEventIds' => $wishlistEventIds,
            'tags' => Tag::with(['translations', 'aliases', 'tagAttachments'])->orderBy('category')->orderBy('slug')->get(),
        ]);
    }
}
