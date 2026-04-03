<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <form
            id="ui-browse-events-form"
            wire:submit.prevent="applySearch"
            class="ui-filter-form ui-filter-form-events space-y-4"
            data-ui="browse-events-form"
        >
            <div class="card border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="browse-events-filters-card">
                <div class="flex flex-wrap items-end gap-4">
                    <x-input
                        id="q"
                        wire:model.defer="q"
                        type="text"
                        :label="__('Search')"
                        :placeholder="__('Name or description…')"
                        class="ui-field ui-field-search w-full max-w-xs"
                        :omit-error="true"
                        data-ui="browse-events-search-input"
                    />
                    @if ($tags->isNotEmpty())
                        <x-form-select id="tag_id" wire:model.defer="tag_id" :label="__('Tag')" class="ui-field ui-field-tag" data-ui="browse-events-tag-select">
                            <option value="">{{ __('Any') }}</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}">
                                    {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
                                </option>
                            @endforeach
                        </x-form-select>
                    @endif
                    <input type="hidden" id="bbox_min_lat" value="{{ $min_lat ?? '' }}">
                    <input type="hidden" id="bbox_max_lat" value="{{ $max_lat ?? '' }}">
                    <input type="hidden" id="bbox_min_lng" value="{{ $min_lng ?? '' }}">
                    <input type="hidden" id="bbox_max_lng" value="{{ $max_lng ?? '' }}">
                    <x-button id="ui-browse-events-submit" type="submit" class="btn-primary ui-action ui-action-search" data-ui="browse-events-search-submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="applySearch">{{ __('Search') }}</span>
                        <span wire:loading wire:target="applySearch">{{ __('Searching…') }}</span>
                    </x-button>
                    @if ($this->hasActiveFilters())
                        <x-button id="ui-browse-events-clear" type="button" wire:click="clearFilters" class="btn-ghost ui-action ui-action-clear" data-ui="browse-events-clear">{{ __('Clear') }}</x-button>
                    @endif
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

        @auth
            <div class="mb-1 flex justify-end">
                <x-button id="ui-browse-events-create" :link="route('events.create')" class="btn-primary ui-action ui-action-create" data-ui="browse-events-create">{{ __('Create event') }}</x-button>
            </div>
        @endauth

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
