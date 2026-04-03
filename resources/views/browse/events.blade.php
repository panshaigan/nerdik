<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Browse events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <form method="GET" class="space-y-4">
                <div class="card border border-base-300 bg-base-100 p-4 shadow-sm">
                    <div class="flex flex-wrap items-end gap-4">
                        <x-input
                            id="q"
                            name="q"
                            type="text"
                            value="{{ request('q') }}"
                            :label="__('Search')"
                            :placeholder="__('Name or description…')"
                            class="w-full max-w-xs"
                            :omit-error="true"
                        />
                        @if ($tags->isNotEmpty())
                            <x-form-select id="tag_id" name="tag_id" :label="__('Tag')">
                                <option value="">{{ __('Any') }}</option>
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}" @selected(request('tag_id') == $tag->id)>
                                        {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
                                    </option>
                                @endforeach
                            </x-form-select>
                        @endif
                        <input type="hidden" name="min_lat" id="bbox_min_lat" value="{{ request('min_lat') }}">
                        <input type="hidden" name="max_lat" id="bbox_max_lat" value="{{ request('max_lat') }}">
                        <input type="hidden" name="min_lng" id="bbox_min_lng" value="{{ request('min_lng') }}">
                        <input type="hidden" name="max_lng" id="bbox_max_lng" value="{{ request('max_lng') }}">
                        <x-button type="submit" class="btn-primary">{{ __('Search') }}</x-button>
                        @if (request()->hasAny(['q', 'tag_id', 'min_lat', 'max_lat', 'min_lng', 'max_lng']))
                            <x-button :link="route('browse.events')" class="btn-ghost">{{ __('Clear') }}</x-button>
                        @endif
                    </div>
                </div>

                <details class="card border border-base-300 bg-base-100 p-4 shadow-sm" @if (request()->filled('min_lat') && request()->filled('max_lat') && request()->filled('min_lng') && request()->filled('max_lng')) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-base-content">{{ __('Filter by map area') }}</summary>
                    <div class="mt-4 space-y-3">
                        <p class="text-sm opacity-80">{{ __('Draw a rectangle on the map, then click Search. Only events linked to a place with coordinates inside the area are shown.') }}</p>
                        <div
                            data-browse-bbox-map
                            class="leaflet-container z-0 h-80 min-h-[280px] w-full rounded-lg border border-base-300"
                        ></div>
                    </div>
                </details>
            </form>

            @auth
                <div class="mb-1 flex justify-end">
                    <x-button :link="route('events.create')" class="btn-primary">{{ __('Create event') }}</x-button>
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
</x-app-layout>
