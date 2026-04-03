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
                'address' => (string) ($row['address'] ?? ''),
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
            <x-input
                label="{{ __('Name') }}"
                name="name"
                type="text"
                value="{{ old('name', $event->name ?? '') }}"
                error-field="name"
                required
                autocomplete="off"
                data-event-name-input
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="event-name-suggestions-popup"
            />
            <div id="event-name-suggestions-popup"
                 class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                 data-event-name-popup
                 role="listbox"></div>
        </div>

        <div class="relative sm:col-span-1">
            <input type="hidden" name="organization_id"
                   value="{{ old('organization_id', $event->organization_id ?? '') }}"
                   data-event-org-id />
            <x-input
                label="{{ __('Organization (optional)') }}"
                name="organization_name"
                type="text"
                value="{{ old('organization_name', optional($event->organization)->name ?? '') }}"
                error-field="organization_name"
                autocomplete="off"
                data-event-org-input
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="event-org-suggestions-popup"
            />
            <div id="event-org-suggestions-popup"
                 class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                 data-event-org-popup
                 role="listbox"></div>
            <x-field-error :messages="$errors->get('organization_id')" class="mt-2" />
            <x-field-error :messages="$errors->get('organization_name')" class="mt-2" />
        </div>
    </div>

    <div>
        <p class="fieldset-legend mb-0.5 font-medium">{{ __('Description (optional)') }}</p>
        <input id="desc" type="hidden" name="desc" value="{{ old('desc', $event->desc ?? '') }}">
        <div class="mt-1 overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <div data-event-desc-editor class="min-h-[11rem]"></div>
        </div>
        <x-field-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-12">
        <div class="lg:col-span-3 lg:max-w-[14rem]">
            <x-input
                label="{{ __('Starts at') }}"
                name="starts_at"
                type="datetime-local"
                value="{{ old('starts_at', $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '') }}"
                error-field="starts_at"
                required
                data-event-start-at
                data-enforce-future="{{ $enforceFutureDates ? '1' : '0' }}"
                class="w-full"
            />
        </div>

        <div class="lg:col-span-3 lg:max-w-[14rem]">
            <x-input
                label="{{ __('Ends at') }}"
                name="ends_at"
                type="datetime-local"
                value="{{ old('ends_at', $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '') }}"
                error-field="ends_at"
                required
                data-event-ends-at
                class="w-full"
            />
        </div>

        <div class="rounded-lg border border-base-300 bg-base-100 p-3 lg:col-span-6">
            <x-checkbox
                id="is_public"
                name="is_public"
                value="1"
                :label="__('Public event')"
                :hint="__('When checked, this event is visible in public lists. If unchecked, it is hidden from those lists.')"
                :checked="(bool) old('is_public', $event->is_public ?? true)"
            />
        </div>
    </div>

    <div>
        <p class="fieldset-legend font-medium text-base-content">{{ __('Where (optional)') }}</p>
        <p class="mb-3 text-sm text-base-content/80">
            {{ __('Click saved-place markers to toggle them (several allowed). Search lists your places, venues you add on this form, and map results. Double-click empty map to add a venue — the name field is focused so you can type (e.g. a pub not in OpenStreetMap). After saving, those places appear under your places for future events.') }}
        </p>

        <div data-event-places-unified class="space-y-3">
            <script type="application/json" data-ep-config>@json($eventPlacesConfig)</script>
            <div class="relative z-[1000]">
                <x-input
                    type="search"
                    data-ep-search
                    autocomplete="off"
                    :placeholder="__('Search places or address… (double-click map to add)')"
                    class="w-full"
                    :omit-error="true"
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
                class="{{ count($initialNewPlaces) ? '' : 'hidden' }} space-y-2 rounded-lg border border-warning/30 bg-warning/5 p-3"
            >
                <p class="text-xs font-medium text-base-content" data-ep-new-heading>{{ __('New venues (created when you save)') }}</p>
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

    @if (isset($tags))
        <div class="mt-4 border-t border-base-300 pt-4">
            <p class="fieldset-legend font-medium text-base-content">{{ __('Tags') }}</p>
            <p class="mb-3 text-xs text-base-content/70">{{ __('Select tags that describe this event (games, themes, etc.).') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $event->exists ? $event->tags->pluck('id')->toArray() : []),
            ])
            <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
        </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const eventForm = document.querySelector('form[data-event-form]');
        if (eventForm) {
            eventForm.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const t = e.target;
                if (t.closest('.ql-editor') || t.closest('.ql-toolbar')) return;
                if (t.tagName === 'TEXTAREA') return;
                if (t.tagName === 'BUTTON') return;
                if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) return;
                if (t.hasAttribute('data-ts-input')) return;
                if (t.hasAttribute('data-ep-search')) return;
                if (t.hasAttribute('data-event-name-input')) return;
                if (t.hasAttribute('data-event-org-input')) return;
                if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                    e.preventDefault();
                }
            });
        }

        const input = document.querySelector('[data-event-name-input]');
        const popup = document.querySelector('[data-event-name-popup]');
        const startsAtEl = document.querySelector('[data-event-start-at]');
        const endsAtEl = document.querySelector('[data-event-ends-at]');
        const descInput = document.getElementById('desc');
        const descEditorEl = document.querySelector('[data-event-desc-editor]');

        if (input && popup) {
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
                    e.preventDefault();
                    if (active >= 0 && active < shown.length) {
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
        }

        const orgInput = document.querySelector('[data-event-org-input]');
        const orgPopup = document.querySelector('[data-event-org-popup]');
        const orgIdInput = document.querySelector('[data-event-org-id]');
        if (orgInput && orgPopup && orgIdInput) {
            const orgSuggestions = @json($organizationSuggestions ?? []);
            let orgShown = [];
            let orgActive = -1;
            let selectedOrg = null;
            const oid = orgIdInput.value.trim();
            const oname = orgInput.value.trim();
            if (oid !== '' && oname !== '') {
                selectedOrg = { id: parseInt(oid, 10), name: oname };
            }

            function syncOrgSelectionFromInput() {
                const t = orgInput.value.trim();
                if (selectedOrg && t.toLowerCase() !== selectedOrg.name.toLowerCase()) {
                    orgIdInput.value = '';
                    selectedOrg = null;
                }
            }

            function closeOrgPopup() {
                orgPopup.classList.add('hidden');
                orgPopup.innerHTML = '';
                orgActive = -1;
                orgInput.setAttribute('aria-expanded', 'false');
            }

            function openOrgPopup() {
                if (orgShown.length === 0) {
                    closeOrgPopup();
                    return;
                }
                orgPopup.classList.remove('hidden');
                orgInput.setAttribute('aria-expanded', 'true');
            }

            function applyOrgActive() {
                [...orgPopup.querySelectorAll('[data-org-suggestion-idx]')].forEach((el, idx) => {
                    const isActive = idx === orgActive;
                    el.classList.toggle('bg-base-200', isActive);
                    el.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            }

            function chooseOrg(item) {
                orgInput.value = item.name;
                orgIdInput.value = String(item.id);
                selectedOrg = { id: item.id, name: item.name };
                closeOrgPopup();
            }

            function renderOrg(items) {
                orgShown = items.slice(0, 8);
                orgPopup.innerHTML = '';
                orgActive = -1;

                if (orgShown.length === 0) {
                    closeOrgPopup();
                    return;
                }

                const frag = document.createDocumentFragment();
                orgShown.forEach((item, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                    btn.textContent = item.name;
                    btn.dataset.orgSuggestionIdx = String(idx);
                    btn.setAttribute('role', 'option');
                    btn.setAttribute('aria-selected', 'false');
                    btn.addEventListener('mousedown', (e) => e.preventDefault());
                    btn.addEventListener('click', () => chooseOrg(item));
                    frag.appendChild(btn);
                });
                orgPopup.appendChild(frag);
                openOrgPopup();
            }

            function updateOrgFromInput() {
                syncOrgSelectionFromInput();
                const q = orgInput.value.trim().toLowerCase();
                if (q.length < 1) {
                    renderOrg(orgSuggestions.slice(0, 8));
                    return;
                }

                const items = orgSuggestions.filter(
                    (o) => o.name.toLowerCase().includes(q) && o.name.toLowerCase() !== q
                );
                renderOrg(items);
            }

            orgInput.addEventListener('input', updateOrgFromInput);
            orgInput.addEventListener('focus', updateOrgFromInput);
            orgInput.addEventListener('keydown', (e) => {
                if (orgPopup.classList.contains('hidden') || orgShown.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    orgActive = (orgActive + 1) % orgShown.length;
                    applyOrgActive();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    orgActive = orgActive <= 0 ? orgShown.length - 1 : orgActive - 1;
                    applyOrgActive();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (orgActive >= 0 && orgActive < orgShown.length) {
                        chooseOrg(orgShown[orgActive]);
                    }
                } else if (e.key === 'Escape') {
                    closeOrgPopup();
                }
            });

            document.addEventListener('click', (e) => {
                if (!orgPopup.contains(e.target) && e.target !== orgInput) {
                    closeOrgPopup();
                }
            });
        }

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
    <x-button :link="route('events.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
