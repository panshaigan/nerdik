<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <form
            id="ui-browse-events-form"
            wire:submit.prevent="applySearch"
            class="ui-filter-form ui-filter-form-events space-y-4"
            data-ui="browse-events-form"
        >
            <div class="card border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="browse-events-filters-card">
                @auth
                    <div class="mb-4 flex justify-end">
                        <a
                            href="{{ route('events.create') }}"
                            wire:navigate
                            class="btn btn-circle btn-primary shadow-md"
                            title="{{ __('Create event') }}"
                            aria-label="{{ __('Create event') }}"
                            data-ui="browse-events-create"
                        >
                            <span class="text-xl font-light leading-none" aria-hidden="true">+</span>
                        </a>
                    </div>
                @endauth

                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                    <div class="min-w-0 flex-1 lg:max-w-xl">
                        @include('livewire.browse.partials.tag-filter')
                    </div>
                    <div class="flex w-full min-w-0 flex-wrap items-end gap-3 lg:ms-auto lg:w-auto lg:max-w-2xl lg:shrink-0 lg:justify-end">
                        <x-input
                            id="q"
                            wire:model.defer="q"
                            type="text"
                            :label="null"
                            :placeholder="__('Name or description…')"
                            class="ui-field ui-field-search min-w-[12rem] flex-1 sm:max-w-xs"
                            :omit-error="true"
                            data-ui="browse-events-search-input"
                        />
                        <input type="hidden" id="bbox_min_lat" value="{{ $min_lat ?? '' }}">
                        <input type="hidden" id="bbox_max_lat" value="{{ $max_lat ?? '' }}">
                        <input type="hidden" id="bbox_min_lng" value="{{ $min_lng ?? '' }}">
                        <input type="hidden" id="bbox_max_lng" value="{{ $max_lng ?? '' }}">
                        <x-button id="ui-browse-events-submit" type="submit" class="btn-primary ui-action ui-action-search shrink-0" data-ui="browse-events-search-submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="applySearch">{{ __('Search') }}</span>
                            <span wire:loading wire:target="applySearch">{{ __('Searching…') }}</span>
                        </x-button>
                        @if ($this->hasActiveFilters())
                            <x-button id="ui-browse-events-clear" type="button" wire:click="clearFilters" class="btn-ghost ui-action ui-action-clear shrink-0" data-ui="browse-events-clear">{{ __('Clear') }}</x-button>
                        @endif
                    </div>
                </div>
            </div>

            <details class="card border border-base-300 bg-base-100 p-4 shadow-sm" @if ($this->hasBBox()) open @endif>
                <summary class="cursor-pointer text-sm font-medium text-base-content">{{ __('Filter by map area') }}</summary>
                <div class="mt-4 space-y-3">
                    <p class="text-sm opacity-80">{{ __('Draw a rectangle on the map, then click Search. Only events linked to a place with coordinates inside the area are shown.') }}</p>
                    <div
                        id="ui-browse-events-map"
                        data-browse-bbox-map
                        wire:ignore
                        class="leaflet-container z-0 h-80 min-h-[280px] w-full rounded-lg border border-base-300"
                        data-ui="browse-events-map"
                    ></div>
                </div>
            </details>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($events as $event)
                <x-cards.event-card :event="$event" :wishlist-event-ids="$wishlistEventIds ?? []" />
            @empty
                <div class="col-span-full">
                    <div class="rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('No public events found.') }}
                        @auth
                            <div class="mt-3">
                                <a href="{{ route('events.create') }}" class="link link-primary">{{ __('Create one') }}</a>
                            </div>
                        @endauth
                    </div>
                </div>
            @endforelse
        </div>

        @if ($events->hasPages())
            <div class="mt-6 rounded-xl border border-base-300 bg-base-100 p-4">{{ $events->links() }}</div>
        @endif
    </div>
</div>
