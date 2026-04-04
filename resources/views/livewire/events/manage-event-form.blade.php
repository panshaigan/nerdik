@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

<form wire:submit.prevent="save" class="space-y-4" data-event-form>
<div id="ui-event-form-fields" class="ui-form ui-form-event space-y-4" data-ui="event-form-fields">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="relative sm:col-span-2">
            <x-input
                wire:model.live.debounce.300ms="name"
                label="{{ __('Name') }}"
                type="text"
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
                 wire:ignore
                 role="listbox"></div>
        </div>

        <div class="relative sm:col-span-1">
            <input type="hidden" wire:model="organization_id" data-event-org-id />
            <x-input
                wire:model.live.debounce.300ms="organization_name"
                label="{{ __('Organization (optional)') }}"
                type="text"
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
                 wire:ignore
                 role="listbox"></div>
            <x-field-error :messages="$errors->get('organization_id')" class="mt-2" />
            <x-field-error :messages="$errors->get('organization_name')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-editor
            wire:model="desc"
            :label="__('Description (optional)')"
            :gpl-license="true"
        />
        <x-field-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-12">
        <div class="lg:col-span-3 lg:max-w-[14rem]">
            <x-input
                wire:model="starts_at"
                label="{{ __('Starts at') }}"
                type="datetime-local"
                error-field="starts_at"
                required
                data-event-start-at
                data-enforce-future="{{ $enforceFutureDates ? '1' : '0' }}"
                class="w-full"
            />
        </div>

        <div class="lg:col-span-3 lg:max-w-[14rem]">
            <x-input
                wire:model="ends_at"
                label="{{ __('Ends at') }}"
                type="datetime-local"
                error-field="ends_at"
                required
                data-event-ends-at
                class="w-full"
            />
        </div>

        <div class="rounded-lg border border-base-300 bg-base-100 p-3 lg:col-span-6">
            <x-checkbox
                id="is_public"
                wire:model="is_public"
                :label="__('Public event')"
                :hint="__('When checked, this event is visible in public lists. If unchecked, it is hidden from those lists.')"
            />
        </div>
    </div>

    <div>
        <p class="fieldset-legend font-medium text-base-content">{{ __('Where (optional)') }}</p>
        <p class="mb-3 text-sm text-base-content/80">
            {{ __('Click saved-place markers to toggle them (several allowed). Search lists your places, venues you add on this form, and map results. Double-click empty map to add a venue — the name field is focused so you can type (e.g. a pub not in OpenStreetMap). After saving, those places appear under your places for future events.') }}
        </p>

        <div id="ui-event-places-section" data-event-places-unified class="ui-event-places space-y-3" data-ui="event-places-section" wire:ignore>
            <script type="application/json" data-ep-config>@json($eventPlacesConfig)</script>
            <div class="relative z-[1000]">
                <x-input
                    type="search"
                    data-ep-search
                    autocomplete="off"
                    :placeholder="__('Search places or address… (double-click map to add)')"
                    class="ui-field ui-field-event-place-search w-full"
                    :omit-error="true"
                    id="ui-event-place-search"
                    data-ui="event-place-search"
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

    <div class="mt-4 border-t border-base-300 pt-4" data-ui="event-signup-periods-section">
        <p class="fieldset-legend font-medium text-base-content">{{ __('ui.events.signup_periods_heading') }}</p>
        <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.signup_periods_help') }}</p>

        <div class="space-y-3">
            @foreach ($signup_periods as $index => $row)
                <div wire:key="signup-period-{{ $index }}" class="rounded-lg border border-base-300 bg-base-100/80 p-3 sm:p-4">
                    <div class="flex min-w-0 flex-nowrap items-end gap-2 overflow-x-auto pb-0.5 sm:gap-3">
                        <div class="min-w-[11rem] shrink-0 sm:min-w-0 sm:flex-1">
                            <x-input
                                wire:model="signup_periods.{{ $index }}.starts_at"
                                type="datetime-local"
                                :label="__('ui.events.signup_period_starts')"
                                class="w-full min-w-0"
                            />
                            <x-field-error :messages="$errors->get('signup_periods.'.$index)" class="mt-2" />
                        </div>
                        <div class="min-w-[11rem] shrink-0 sm:min-w-0 sm:flex-1">
                            <x-input
                                wire:model="signup_periods.{{ $index }}.ends_at"
                                type="datetime-local"
                                :label="__('ui.events.signup_period_ends')"
                                class="w-full min-w-0"
                                :max="$eventSignupPeriodMax ?? null"
                            />
                        </div>
                        <div class="min-w-[6.5rem] max-w-[9rem] shrink-0">
                            <x-input
                                wire:model.live="signup_periods.{{ $index }}.max_activities"
                                type="number"
                                min="0"
                                step="1"
                                :label="__('ui.events.signup_period_max_activities')"
                                class="w-full"
                            />
                            <x-field-error :messages="$errors->get('signup_periods.'.$index.'.max_activities')" class="mt-2" />
                        </div>
                        <div class="ml-auto flex shrink-0 justify-end self-end pb-1">
                            <button
                                type="button"
                                class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                wire:click="removeSignupPeriod({{ $index }})"
                                wire:loading.attr="disabled"
                                title="{{ __('Remove') }}"
                                aria-label="{{ __('Remove') }}"
                                data-ui="event-signup-period-remove"
                            >
                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244-2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <x-button type="button" class="btn-outline btn-sm mt-2" wire:click="addSignupPeriod" wire:loading.attr="disabled">
            {{ __('ui.events.signup_period_add') }}
        </x-button>

        <x-field-error :messages="$errors->get('signup_periods')" class="mt-2" />
    </div>

    <div class="mt-4 border-t border-base-300 pt-4">
            <p class="fieldset-legend font-medium text-base-content">{{ __('Tags') }}</p>
            <p class="mb-3 text-xs text-base-content/70">{{ __('Select tags that describe this event (games, themes, etc.).') }}</p>
            <div wire:ignore>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => $tag_ids,
            ])
            </div>
            <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
    </div>
</div>

        <div id="ui-event-form-actions" class="ui-form-actions mt-6 flex justify-end gap-3" data-ui="event-form-actions">
            <x-button id="ui-event-cancel" :link="$cancelUrl" class="btn-outline ui-action ui-action-cancel" data-ui="event-cancel">{{ __('Cancel') }}</x-button>

            <x-button id="ui-event-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="event-submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $submitLabel }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-button>
        </div>
</form>

@push('scripts')
<script>
(() => {
    /** Livewire wire:navigate does not fire DOMContentLoaded again; init must run on navigated + when DOM is already ready. */
    let manageEventFormScriptsAbort;
    function initManageEventFormScripts() {
        manageEventFormScriptsAbort?.abort();
        manageEventFormScriptsAbort = new AbortController();
        const signal = manageEventFormScriptsAbort.signal;

        const eventForm = document.querySelector('form[data-event-form]');
        if (eventForm) {
            eventForm.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const t = e.target;
                if (t.closest('.tox-tinymce') || t.closest('.tox-toolbar')) return;
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
            }, { signal });
        }

        const input = document.querySelector('[data-event-name-input]');
        const popup = document.querySelector('[data-event-name-popup]');
        const startsAtEl = document.querySelector('[data-event-start-at]');
        const endsAtEl = document.querySelector('[data-event-ends-at]');

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
                input.dispatchEvent(new Event('input', { bubbles: true }));
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
                    const items = suggestions.slice(0, 8);
                    render(items);
                    return;
                }

                const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
                render(items);
            }

            input.addEventListener('input', updateFromInput, { signal });
            input.addEventListener('focus', updateFromInput, { signal });
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
            }, { signal });

            document.addEventListener('click', (e) => {
                if (!popup.contains(e.target) && e.target !== input) {
                    closePopup();
                }
            }, { signal });
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
                    orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
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
                orgInput.dispatchEvent(new Event('input', { bubbles: true }));
                orgIdInput.value = String(item.id);
                orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
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

            orgInput.addEventListener('input', updateOrgFromInput, { signal });
            orgInput.addEventListener('focus', updateOrgFromInput, { signal });
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
            }, { signal });

            document.addEventListener('click', (e) => {
                if (!orgPopup.contains(e.target) && e.target !== orgInput) {
                    closeOrgPopup();
                }
            }, { signal });
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
                endsAtEl.dispatchEvent(new Event('input', { bubbles: true }));
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
            startsAtEl.addEventListener('change', syncDateGuards, { signal });
            startsAtEl.addEventListener('input', syncDateGuards, { signal });
            endsAtEl.addEventListener('change', syncDateGuards, { signal });
            endsAtEl.addEventListener('input', syncDateGuards, { signal });

            const form = startsAtEl.closest('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    syncDateGuards();
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        form.reportValidity();
                    }
                }, { signal });
            }
        }

    }

    document.addEventListener('livewire:navigating', () => {
        manageEventFormScriptsAbort?.abort();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initManageEventFormScripts, { once: true });
    } else {
        initManageEventFormScripts();
    }
    document.addEventListener('livewire:navigated', initManageEventFormScripts);
})();
</script>
@endpush
