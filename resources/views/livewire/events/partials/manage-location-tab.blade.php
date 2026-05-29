<div class="">
    <p class="mb-6 text-sm text-base-content/80">
        {{ __('ui.events.location_tab_help') }}
    </p>

    <div id="ui-event-places-section" data-event-places-unified class="ui-event-places space-y-3" data-ui="event-places-section" wire:ignore>
        <script type="application/json" data-ep-config>@json($eventPlacesConfig)</script>
        <div class="relative z-[1000]">
            <x-input
                type="search"
                data-ep-search
                autocomplete="off"
                :label="__('ui.slots.venue_optional')"
                :placeholder="__('ui.activities.self_hosted_place_search_placeholder')"
                class="ui-field ui-field-event-place-search w-full"
                :omit-error="true"
                id="ui-event-place-search"
                data-ui="event-place-search"
                inline
            />
            <div
                id="ui-event-place-search-results"
                data-ep-results
                class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                data-ui="event-place-search-results"
            ></div>
        </div>

        <div
            id="ui-event-places-map"
            data-ep-map
            class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30"
            style="min-height: 280px; height: min(420px, 50vh);"
            data-ui="event-places-map"
        ></div>

        <div data-ep-chips class="flex min-h-[1.5rem] flex-wrap gap-2"></div>

        <div
            data-ep-new-venues-wrap
            class="{{ count($eventPlacesConfig['initialNewPlaces'] ?? []) ? '' : 'hidden' }} space-y-2 rounded-lg border border-warning/30 bg-warning/5 p-3"
        >
            <p class="text-xs font-medium text-base-content" data-ep-new-heading>{{ __('ui.activities.self_hosted_new_venues_label') }}</p>
            <div data-ep-new-venues class="space-y-3"></div>
        </div>

        <div data-ep-place-ids></div>
    </div>

    <x-field-error :messages="$errors->get('place_ids')" class="mt-2" />
    <x-field-error :messages="$errors->get('place_ids.*')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_places')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_places.*')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_places.*.name')" class="mt-2" />
</div>
