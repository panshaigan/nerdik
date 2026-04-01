@csrf

@php
    $selectedPlaceIds = collect(old('place_ids', isset($event) && $event->exists ? $event->places->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();

    $placesUnified = collect($places ?? [])
        ->map(function ($place) {
            $loc = $place->locationLabel();

            return [
                'id' => (int) $place->id,
                'label' => $loc !== '' ? "{$place->name} ({$loc})" : $place->name,
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
                'city_id' => isset($row['city_id']) && $row['city_id'] !== '' ? (int) $row['city_id'] : null,
                'country_id' => isset($row['country_id']) && $row['country_id'] !== '' ? (int) $row['country_id'] : null,
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
    $enforceFutureDates = ! ($event->exists ?? false);
@endphp

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="relative sm:col-span-2">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1"
                          value="{{ old('name', $event->name ?? '') }}"
                          autocomplete="off"
                          data-event-name-input
                          aria-autocomplete="list"
                          aria-expanded="false"
                          aria-controls="event-name-suggestions-popup"
                          required />
            <div id="event-name-suggestions-popup"
                 class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                 data-event-name-popup
                 role="listbox"></div>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="organization_id" :value="__('Organization (optional)')" />
            <select id="organization_id" name="organization_id" class="select select-bordered mt-1 w-full rounded-md border-base-300 bg-base-100 text-base-content">
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
    </div>

    <div>
        <x-input-label for="desc" :value="__('Description (optional)')" />
        <input id="desc" type="hidden" name="desc" value="{{ old('desc', $event->desc ?? '') }}">
        <div class="mt-1">
            <div data-event-desc-editor class="overflow-hidden rounded-md border border-base-300 bg-base-100"></div>
        </div>
        <x-input-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts at')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1" data-event-start-at data-enforce-future="{{ $enforceFutureDates ? '1' : '0' }}"
                          value="{{ old('starts_at', $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_at" :value="__('Ends at')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1" data-event-ends-at
                          value="{{ old('ends_at', $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
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
                    class="input input-bordered w-full rounded-md border-base-300 bg-base-100"
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
                class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30"
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

    @if (isset($tags))
        <div class="mt-4 border-t border-base-300 pt-4">
            <x-input-label :value="__('Tags')" />
            <p class="mb-3 text-xs text-base-content/70">{{ __('Select tags that describe this event (games, themes, etc.).') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $event->exists ? $event->tags->pluck('id')->toArray() : []),
            ])
            <x-input-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
        </div>
    @endif

    <div class="rounded-lg border border-base-300 bg-base-100 p-3">
        <label for="is_public" class="flex items-start gap-3">
            <input id="is_public" name="is_public" type="checkbox" value="1" class="mt-1"
                   @checked(old('is_public', $event->is_public ?? true)) />
            <span>
                <span class="block text-sm font-medium text-base-content">{{ __('Public event') }}</span>
                <span class="block text-xs text-base-content/70">
                    {{ __('When checked, this event is visible in public lists. If unchecked, it is hidden from those lists.') }}
                </span>
            </span>
        </label>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const input = document.querySelector('[data-event-name-input]');
        const popup = document.querySelector('[data-event-name-popup]');
        const startsAtEl = document.querySelector('[data-event-start-at]');
        const endsAtEl = document.querySelector('[data-event-ends-at]');
        const descInput = document.getElementById('desc');
        const descEditorEl = document.querySelector('[data-event-desc-editor]');
        if (!input || !popup) return;

        const suggestions = @json($nameSuggestions ?? []);
        let shown = [];
        let active = -1;

        function closePopup() {
            popup.classList.add('hidden');
            popup.innerHTML = '';
            active = -1;
            input.setAttribute('aria-expanded', 'false');
        }

        function openPopup() {
            if (shown.length === 0) {
                closePopup();
                return;
            }
            popup.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }

        function applyActive() {
            [...popup.querySelectorAll('[data-suggestion-idx]')].forEach((el, idx) => {
                const isActive = idx === active;
                el.classList.toggle('bg-base-200', isActive);
                el.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        function choose(value) {
            input.value = value;
            closePopup();
        }

        function render(items) {
            shown = items.slice(0, 8);
            popup.innerHTML = '';
            active = -1;

            if (shown.length === 0) {
                closePopup();
                return;
            }

            const frag = document.createDocumentFragment();
            shown.forEach((name, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                btn.textContent = name;
                btn.dataset.suggestionIdx = String(idx);
                btn.setAttribute('role', 'option');
                btn.setAttribute('aria-selected', 'false');
                btn.addEventListener('mousedown', (e) => e.preventDefault());
                btn.addEventListener('click', () => choose(name));
                frag.appendChild(btn);
            });
            popup.appendChild(frag);
            openPopup();
        }

        function updateFromInput() {
            const q = input.value.trim().toLowerCase();
            if (q.length < 1) {
                closePopup();
                return;
            }

            const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
            render(items);
        }

        input.addEventListener('input', updateFromInput);
        input.addEventListener('focus', updateFromInput);
        input.addEventListener('keydown', (e) => {
            if (popup.classList.contains('hidden') || shown.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                active = (active + 1) % shown.length;
                applyActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                active = active <= 0 ? shown.length - 1 : active - 1;
                applyActive();
            } else if (e.key === 'Enter') {
                if (active >= 0 && active < shown.length) {
                    e.preventDefault();
                    choose(shown[active]);
                }
            } else if (e.key === 'Escape') {
                closePopup();
            }
        });

        document.addEventListener('click', (e) => {
            if (!popup.contains(e.target) && e.target !== input) {
                closePopup();
            }
        });

        function nowForDateTimeLocal() {
            const d = new Date();
            d.setSeconds(0);
            d.setMilliseconds(0);
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function syncDateGuards() {
            if (!startsAtEl || !endsAtEl) return;

            const minNow = nowForDateTimeLocal();
            const enforceFuture = startsAtEl.getAttribute('data-enforce-future') === '1';
            startsAtEl.min = enforceFuture ? minNow : '';
            endsAtEl.min = startsAtEl.value || (enforceFuture ? minNow : '');

            if (startsAtEl.value && endsAtEl.value && endsAtEl.value < startsAtEl.value) {
                endsAtEl.value = startsAtEl.value;
            }

            startsAtEl.setCustomValidity('');
            endsAtEl.setCustomValidity('');
            if (enforceFuture && startsAtEl.value && startsAtEl.value < minNow) {
                startsAtEl.setCustomValidity('{{ __('Start date cannot be in the past.') }}');
            }
            if (endsAtEl.value && endsAtEl.value < (startsAtEl.value || (enforceFuture ? minNow : endsAtEl.value))) {
                endsAtEl.setCustomValidity('{{ __('End date cannot be earlier than start date.') }}');
            }
        }

        if (startsAtEl && endsAtEl) {
            syncDateGuards();
            startsAtEl.addEventListener('change', syncDateGuards);
            startsAtEl.addEventListener('input', syncDateGuards);
            endsAtEl.addEventListener('change', syncDateGuards);
            endsAtEl.addEventListener('input', syncDateGuards);

            const form = startsAtEl.closest('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    syncDateGuards();
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        form.reportValidity();
                    }
                });
            }
        }

        if (descInput && descEditorEl && window.Quill) {
            const quill = new window.Quill(descEditorEl, {
                theme: 'snow',
                placeholder: '{{ __('Write event description...') }}',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }, 'blockquote', 'code-block'],
                        ['link'],
                        ['clean'],
                    ],
                },
            });

            const initialHtml = (descInput.value || '').trim();
            if (initialHtml) {
                quill.clipboard.dangerouslyPasteHTML(initialHtml);
            }

            quill.on('text-change', () => {
                const html = quill.root.innerHTML.trim();
                descInput.value = html === '<p><br></p>' ? '' : html;
            });
        }

    });
</script>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('events.index') }}" class="btn btn-outline">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>
