<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="ui-filter-form ui-filter-form-events space-y-4" data-ui="browse-events-form">
            <div class="card border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="browse-events-filters-card">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 w-full flex-1 space-y-2">
                            @include('livewire.browse.partials.listing-type-filter')
                            @include('livewire.browse.partials.tag-filter')
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 lg:pt-1">
                            @include('livewire.browse.partials.tag-filter-toggles')
                        </div>
                    </div>
                </div>
            </div>
            <details class="card border border-base-300 bg-base-100 p-4 shadow-sm" @if ($this->hasBBox()) open @endif>
                <summary class="cursor-pointer text-sm font-medium text-base-content">{{ __('Filter by map area') }}</summary>
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
            </details>
        </div>
        <div class="flex justify-end">
            @include('livewire.browse.partials.sort-controls', ['sortIdPrefix' => 'browse-events'])
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($browseListings as $row)
                @if ($row['kind'] === 'event')
                    <x-cards.event-card
                        :event="$row['event']"
                        :interested-event-ids="$interestedEventIds ?? []"
                        :show-listing-kind="true"
                    />
                @else
                    <x-cards.activity-card
                        :activity="$row['activity']"
                        :interested-activity-ids="$interestedActivityIds ?? []"
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
