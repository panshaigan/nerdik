@csrf

@php
    $selectedPlaceIds = collect(old('place_ids', isset($event) && $event->exists ? $event->places->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();

    $placesUnified = collect($places ?? [])
        ->map(function ($place) {
            $location = trim(implode(', ', array_filter([$place->city, $place->country])));

            return [
                'id' => (int) $place->id,
                'label' => $location !== '' ? "{$place->name} ({$location})" : $place->name,
                'lat' => $place->latitude !== null ? (float) $place->latitude : null,
                'lng' => $place->longitude !== null ? (float) $place->longitude : null,
            ];
        })
        ->values()
        ->all();

    $initialNewPlaces = [];
    $oldNewPlaces = old('new_places');
    if (is_array($oldNewPlaces)) {
        foreach ($oldNewPlaces as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                continue;
            }
            $initialNewPlaces[] = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'name' => (string) ($row['name'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'country' => (string) ($row['country'] ?? ''),
            ];
        }
    }

    $eventPlacesConfig = [
        'places' => $placesUnified,
        'initialSelectedIds' => $selectedPlaceIds,
        'initialNewPlaces' => $initialNewPlaces,
        'searchUrl' => route('geocode.search'),
        'reverseUrl' => route('geocode.reverse'),
        'strings' => [
            'yourPlaces' => __('Your places'),
            'mapSearch' => __('Map search'),
            'noResults' => __('No results'),
            'newVenuesHeading' => __('New venues (created when you save)'),
            'newVenueNumber' => __('Venue'),
            'removeVenue' => __('Remove'),
            'addedThisForm' => __('Added on this form'),
        ],
    ];
@endphp

<div class="space-y-4">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $event->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="organization_id" :value="__('Organization (optional)')" />
        <select id="organization_id" name="organization_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($organizations as $organization)
                <option value="{{ $organization->id }}"
                    @selected((string) old('organization_id', $event->organization_id ?? '') === (string) $organization->id)>
                    {{ $organization->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('organization_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label :value="__('Where (optional)')" />
        <p class="mb-3 text-sm text-base-content/80">
            {{ __('Click saved-place markers to toggle them (several allowed). Search lists your places, venues you add on this form, and map results. Double-click empty map to add a venue — the name field is focused so you can type (e.g. a pub not in OpenStreetMap). After saving, those places appear under your places for future events.') }}
        </p>

        <div data-event-places-unified class="space-y-3">
            <script type="application/json" data-ep-config>@json($eventPlacesConfig)</script>
            <div class="relative z-[1000]">
                <input
                    type="search"
                    data-ep-search
                    class="input input-bordered w-full border-base-300 bg-base-100"
                    placeholder="{{ __('Search places or address… (double-click map to add)') }}"
                    autocomplete="off"
                />
                <div
                    data-ep-results
                    class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                ></div>
            </div>

            <div
                data-ep-map
                class="z-0 w-full overflow-hidden rounded-xl border border-base-300 bg-base-200/30"
                style="min-height: 280px; height: min(420px, 50vh);"
            ></div>

            <div data-ep-chips class="flex min-h-[1.5rem] flex-wrap gap-2"></div>

            <div
                data-ep-new-venues-wrap
                class="{{ count($initialNewPlaces) ? '' : 'hidden' }} space-y-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3"
            >
                <p class="text-xs font-medium text-base-content" data-ep-new-heading>{{ __('New venues (created when you save)') }}</p>
                <div data-ep-new-venues class="space-y-3"></div>
            </div>

            <div data-ep-place-ids></div>
        </div>

        <x-input-error :messages="$errors->get('place_ids')" class="mt-2" />
        <x-input-error :messages="$errors->get('place_ids.*')" class="mt-2" />
        <x-input-error :messages="$errors->get('new_places')" class="mt-2" />
        <x-input-error :messages="$errors->get('new_places.*')" class="mt-2" />
        <x-input-error :messages="$errors->get('new_places.*.name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts at')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('starts_at', $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_at" :value="__('Ends at')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('ends_at', $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="desc" :value="__('Description (optional)')" />
        <textarea id="desc" name="desc" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="3">{{ old('desc', $event->desc ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="is_public" name="is_public" type="checkbox" value="1"
               @checked(old('is_public', $event->is_public ?? true)) />
        <x-input-label for="is_public" :value="__('Public event')" />
    </div>

    @if (isset($tags) && $tags->isNotEmpty())
        <div class="border-t border-gray-200 pt-4 mt-4">
            <x-input-label :value="__('Tags')" />
            <p class="text-xs text-gray-500 mb-3">{{ __('Select tags that describe this event (games, themes, etc.).') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $event->exists ? $event->tags->pluck('id')->toArray() : []),
            ])
            <x-input-error :messages="$errors->get('tag_ids')" class="mt-2" />
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('events.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>
