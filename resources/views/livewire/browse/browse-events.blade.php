<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="ui-filter-form ui-filter-form-events ui-tile-active box-glow-secondary rounded-2xl p-6" data-ui="browse-events-form" x-data="{ filtersOpen: @js($this->hasBBox()) }">
            <div class="" data-ui="browse-events-filters-card">
                <div class="mb-6">
                    @include('livewire.browse.partials.tag-filter-toggles')
                </div>
                <div x-show="filtersOpen" x-cloak class="ui-tile-empty p-6 rounded-2xl shadow-sm mb-6">
                    @include('livewire.browse.partials.listing-type-filter')
                    <div class="mt-4 space-y-3">
                        <input type="hidden" id="bbox_min_lat" value="{{ $min_lat ?? '' }}">
                        <input type="hidden" id="bbox_max_lat" value="{{ $max_lat ?? '' }}">
                        <input type="hidden" id="bbox_min_lng" value="{{ $min_lng ?? '' }}">
                        <input type="hidden" id="bbox_max_lng" value="{{ $max_lng ?? '' }}">
                        <p class="text-sm opacity-80">{{ __('ui.browse.map_bbox_hint') }}</p>
                        <div
                            id="ui-browse-events-map"
                            data-browse-bbox-map
                            wire:ignore
                            class="leaflet-container z-0 h-80 min-h-[280px] w-full rounded-lg border border-base-300"
                            data-ui="browse-events-map"
                        ></div>
                    </div>
                </div>
                <div class="flex items-center gap-4 w-full">
                    <div class="flex-1 min-w-0">
                        @include('livewire.browse.partials.tag-filter')
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end mt-6">
            @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'browse-events'])
        </div>
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
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
            <div class="mt-6 rounded-xl border border-base-300 bg-base-100 p-4">{{ $browseListings->links() }}</div>
        @endif
    </div>
</div>
