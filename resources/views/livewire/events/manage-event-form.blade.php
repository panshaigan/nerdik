@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

@php
    $datetimeMinuteStepSeconds = max(1, (int) config('ui-datetime.minute_step', 5)) * 60;
    $title = $editingEvent ? (__('ui.events.edit_event').': '.$this->name) : __('ui.events.create');
@endphp
<div>
    <div
        class="overflow-hidden rounded border border-base-300 bg-base-100 shadow"
    >
        <div class="relative rounded min-h-[140px] bg-gradient-to-br from-primary/20 via-base-200/50 to-base-100 sm:min-h-[180px] p-6 sm:p-8">
            <x-header
                title="{{ $title }}"
                class=""
                separator
                use-h1
            >
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <a href="/activities/{{$this->slug}}"><x-icon name="o-chevron-left" class="cursor-pointer" /></a>
                        <span>{{ $title }}</span>
                    </div>
                </x-slot:title>
                <x-slot:subtitle>
                    {{__('Placeholder')}}
                </x-slot:subtitle>
                <x-slot:actions>
                    @if ($creator)
                        <x-user-badge
                            :user="$creator"
                            size="md"
                            name-class="truncate text-end font-semibold"
                            data-ui="activity-show-host"
                            title="Creator"
                        />
                    @endif
                </x-slot:actions>
            </x-header>
            <div class="flex justify-end">
                <x-toggle
                    id="is_public"
                    wire:model="is_public"
                    :label="__('Public event')"
                    :hint="__('When checked, this event is visible in public lists. If unchecked, it is hidden from those lists.')"
                />
            </div>
        </div>
        <x-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" />
    </div>
    <x-form wire:submit.prevent="save" data-event-form>
        <div id="ui-event-form-fields" class="ui-form ui-form-event" data-ui="event-form-fields">
            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                data-ui="event-manage-tabs"
            >

                <x-tab name="main-details" :label="__('Main details')" class="px-6 pt-6" data-ui="event-manage-tab-main-details" icon="o-pencil-square">
                    @include('livewire.events.partials.manage-main-details-tab')
                </x-tab>

                <x-tab name="location" :label="__('Location')" class="px-6 pt-6" data-ui="event-manage-tab-location" icon="o-map-pin">
                    @include('livewire.events.partials.manage-location-tab')
                </x-tab>

                <x-tab name="enrollment-windows" :label="__('Enrollment windows')" class="px-6 pt-6" data-ui="event-manage-tab-enrollment-windows" icon="o-calendar">
                    @include('livewire.events.partials.manage-enrollment-windows-tab')
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>

        <x-slot:actions class="px-6 pb-6" id="ui-event-form-actions" data-ui="event-form-actions">
            <x-button id="ui-event-cancel" :link="$cancelUrl" class="btn-outline ui-action ui-action-cancel" data-ui="event-cancel">{{ __('Cancel') }}</x-button>

            <x-button id="ui-event-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="event-submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $submitLabel }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-button>
        </x-slot:actions>
    </x-form>

</div>
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

        const eventTabsRoot = document.querySelector('[data-ui="event-manage-tabs"]');
        const refreshEventMaps = () => {
            document.querySelectorAll('#ui-event-places-section[data-event-places-unified]').forEach((root) => {
                if (typeof root._epScheduleInvalidate === 'function') {
                    root._epScheduleInvalidate();
                } else {
                    window.dispatchEvent(new Event('resize'));
                }
            });
        };
        if (eventTabsRoot) {
            eventTabsRoot.addEventListener('click', (e) => {
                const tabButton = e.target.closest('[role="tab"]');
                if (!tabButton) {
                    return;
                }
                [0, 120, 320, 700].forEach((ms) => {
                    setTimeout(refreshEventMaps, ms);
                });
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
