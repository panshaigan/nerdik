<div class="py-12">
    <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
        <div class="ui-filter-form ui-filter-form-events ui-browse-events-filter-shell ui-gradient-frame-brand-bold rounded-2xl p-6" data-ui="browse-events-form" x-data="{ filtersOpen: false }">
            <div class="" data-ui="browse-events-filters-card">
                <div class="flex items-center gap-4 w-full">
                    <div class="flex-1 min-w-0">
                        @include('livewire.browse.partials.tag-filter')
                    </div>
                </div>
                <div class="flex justify-end browse-events-filter-toolbar mb-6">
                    @include('livewire.browse.partials.tag-filter-toggles')
                </div>
                <div x-show="filtersOpen" x-cloak class="ui-tile-empty p-6 rounded-2xl shadow-sm mb-6">
                    @include('livewire.browse.partials.listing-type-filter')
                </div>
            </div>
        </div>

    </div>
    <div class="sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
        <div class="hidden" aria-hidden="true" wire:key="browse-bbox-inputs">
            <input type="hidden" id="bbox_min_lat" value="{{ $min_lat ?? '' }}">
            <input type="hidden" id="bbox_max_lat" value="{{ $max_lat ?? '' }}">
            <input type="hidden" id="bbox_min_lng" value="{{ $min_lng ?? '' }}">
            <input type="hidden" id="bbox_max_lng" value="{{ $max_lng ?? '' }}">
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'browse-events'])
            </div>
            <x-button
                type="button"
                wire:click="toggleMapView"
                wire:key="browse-events-map-view-toggle"
                class="btn btn-sm rounded-xl {{ $map_view ? 'btn-accent' : 'btn-outline btn-neutral' }}"
                :title="__('ui.browse.map_view_toggle')"
                :aria-label="__('ui.browse.map_view_toggle')"
                aria-pressed="{{ $map_view ? 'true' : 'false' }}"
                data-ui="browse-events-map-toggle"
            >
                <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                {{ __('ui.browse.map_view_toggle') }}
            </x-button>
        </div>

        @if ($map_view)
            <div class="ui-gradient-frame-brand-bold mt-6 w-full overflow-hidden rounded-xl" data-ui="browse-events-map-frame">
                <div
                    id="ui-browse-events-map"
                    data-browse-events-map
                    data-map-features-url="{{ $mapFeaturesUrl }}"
                    data-str-clear="{{ __('ui.browse.map_clear_area') }}"
                    data-map-country-listings="{{ __('ui.browse.map_country_listings') }}"
                    wire:ignore
                    x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('browse-events-map:visible')))"
                    class="leaflet-container z-0 min-h-[min(70vh,640px)] h-[min(70vh,640px)] w-full"
                    data-ui="browse-events-map"
                ></div>
            </div>
        @else
            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 md:gap-8 xl:grid-cols-3">
                @forelse ($browseListings as $row)
                    @if ($row['kind'] === 'event')
                        <x-cards.event-card
                            :event="$row['event']"
                            :interested-event-ids="$interestedEventIds ?? []"
                            :participating-event-ids="$participatingEventIds ?? []"
                            :show-listing-kind="true"
                        />
                    @else
                        <x-cards.activity-card
                            :activity="$row['activity']"
                            :interested-activity-ids="$interestedActivityIds ?? []"
                            :participating-activity-ids="$participatingActivityIds ?? []"
                            :show-listing-kind="true"
                        />
                    @endif
                @empty
                    <div class="col-span-full">
                        <div class="rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                            {{ __('ui.browse.no_events_or_activities') }}
                            @auth
                                @if (auth()->user()?->canCreateEvents())
                                <div class="mt-3">
                                    <a href="{{ route('events.create') }}" class="link link-primary">{{ __('Create one') }}</a>
                                </div>
                                @endif
                            @endauth
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($browseListings->hasPages())
                <div class="ui-gradient-frame-brand-bold mt-6 rounded-xl p-4">{{ $browseListings->links() }}</div>
            @endif
        @endif
    </div>
</div>
