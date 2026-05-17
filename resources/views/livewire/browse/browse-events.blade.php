<div class="py-12">
    <div class="mx-auto w-full max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div
            class="ui-filter-form ui-filter-form-events ui-browse-events-filter-shell"
            data-ui="browse-events-form"
            x-data="{ filtersOpen: false }"
        >
            <div class="flex items-start gap-4">
                <div class="min-w-0 flex-1 space-y-3">
                    @include('livewire.browse.partials.tag-filter', [
                        'fieldShellClass' => 'ui-browse-events-search-shell ui-gradient-frame-brand-bold rounded-2xl',
                    ])
                    <div
                        x-show="filtersOpen"
                        x-cloak
                        class="ui-tile-empty w-full rounded-2xl p-6 shadow-sm"
                        data-ui="browse-events-filters-panel"
                    >
                        @include('livewire.browse.partials.listing-type-filter')
                    </div>
                </div>
                <div class="browse-events-filter-toolbar shrink-0 self-start">
                    @include('livewire.browse.partials.tag-filter-toggles')
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'browse-events'])
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
            <div
                id="ui-browse-events-map"
                data-browse-events-map
                data-map-features-url="{{ $mapFeaturesUrl }}"
                data-str-clear="{{ __('ui.browse.map_clear_area') }}"
                data-map-country-listings="{{ __('ui.browse.map_country_listings') }}"
                data-map-popup-details="{{ __('ui.browse.map_popup_details') }}"
                data-bbox-min-lat="{{ $min_lat ?? '' }}"
                data-bbox-max-lat="{{ $max_lat ?? '' }}"
                data-bbox-min-lng="{{ $min_lng ?? '' }}"
                data-bbox-max-lng="{{ $max_lng ?? '' }}"
                wire:ignore
                wire:key="browse-events-map-root"
                x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('browse-events-map:visible')))"
                class="leaflet-container z-0 mt-6 min-h-[min(70vh,640px)] h-[min(70vh,640px)] w-full overflow-hidden rounded-xl"
                data-ui="browse-events-map"
            >
                <div class="hidden" aria-hidden="true" wire:key="browse-bbox-inputs">
                    <input type="hidden" id="bbox_min_lat" value="{{ $min_lat ?? '' }}">
                    <input type="hidden" id="bbox_max_lat" value="{{ $max_lat ?? '' }}">
                    <input type="hidden" id="bbox_min_lng" value="{{ $min_lng ?? '' }}">
                    <input type="hidden" id="bbox_max_lng" value="{{ $max_lng ?? '' }}">
                </div>
            </div>
        @else
            <div
                class="ui-browse-events-listings grid grid-cols-1 gap-4 md:grid-cols-4 md:gap-6"
                data-ui="browse-events-listings"
            >
                @forelse ($browseListings as $row)
                    @php
                        $listing = $row['kind'] === 'event' ? $row['event'] : $row['activity'];
                    @endphp
                    <div wire:key="browse-listing-{{ $row['kind'] }}-{{ $listing->id }}" class="contents">
                        <x-cards.listing-card
                            :listing="$listing"
                            :interested-ids="$row['kind'] === 'event' ? ($interestedEventIds ?? []) : ($interestedActivityIds ?? [])"
                            :show-listing-kind="true"
                        />
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('ui.browse.no_events_or_activities') }}
                        @auth
                            @if (auth()->user()?->canCreateEvents())
                                <div class="mt-3">
                                    <a href="{{ route('events.create') }}" class="link link-primary">{{ __('Create one') }}</a>
                                </div>
                            @endif
                        @endauth
                    </div>
                @endforelse
            </div>

            @if ($browseListings->hasPages())
                <div class="ui-browse-events-pagination rounded-xl p-4" data-ui="browse-events-pagination">
                    {{ $browseListings->links() }}
                </div>
            @endif
        @endif
    </div>

    @include('livewire.partials.listing-preview-modals')
</div>
