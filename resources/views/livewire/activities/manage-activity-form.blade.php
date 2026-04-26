@php
    $title = $editingActivityId ? (__('ui.activities.edit_activity').': '.$this->name) : __('ui.activities.create_activity');
    $isCancelled = $editingActivity?->isCancelled();
@endphp
@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush
<div>
    <div
        class="ui-activity-show-hero overflow-hidden rounded border border-base-300 bg-base-100 shadow"
        data-ui="activity-show-hero"
    >
        <div class="relative rounded min-h-[140px] bg-gradient-to-br from-primary/20 via-base-200/50 to-base-100 sm:min-h-[180px] p-6 sm:p-8">
            <x-header
                title="{{ $title }}"
                class=""
                separator
                use-h1
            >
                <x-slot:title>
                    @if ($this->slug)
                        <div class="flex items-center gap-2">
                            <a href="/activities/{{$this->slug}}"><x-icon name="o-chevron-left" class="cursor-pointer" /></a>
                            <span>{{ $title }}</span>
                        </div>
                    @endif
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
            @if ($isCancelled)
                <div role="alert" class="alert text-sm mb-6">
                    <div class="space-y-1">
                        <p class="font-medium">{{ __('ui.activities.cancelled_badge') }}</p>
                        @if ($editingActivity->cancel_reason)
                            <p>{{ __('ui.activities.cancel_reason_label') }}: {{ $editingActivity->cancel_reason }}</p>
                        @endif
                        <p class="opacity-80">
                            {{ __('ui.activities.cancelled_meta', [
                                'who' => $editingActivity->canceller?->nickname ?? $editingActivity->canceller?->email ?? __('ui.common.unknown_user'),
                                'when' => $editingActivity->cancelled_at ? format_datetime_in_user_tz($editingActivity->cancelled_at) : '—',
                            ]) }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
        <x-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" />
    </div>
    <x-form wire:submit.prevent="save" class="" data-activity-form>
        <div id="ui-activity-form-fields" class="ui-form ui-form-activity space-y-4" data-ui="activity-form-fields">
            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                data-ui="activity-manage-tabs"
            >
                <x-tab name="main-details" :label="__('Main details')" class="px-6 py-6" data-ui="activity-manage-tab-main-details" icon="o-pencil-square">
                    @include('livewire.activities.partials.manage-main-details-tab')
                </x-tab>

                <x-tab name="tags" :label="__('ui.activities.tags')" class="px-6 py-6" data-ui="activity-manage-tab-tags" icon="o-tag">
                    @include('livewire.activities.partials.manage-tags-tab')
                </x-tab>

                <x-tab name="hosting-mode" :label="__('ui.activities.hosting_mode_label')" class="px-6 py-6" data-ui="activity-manage-tab-hosting" icon="o-map-pin">
                    @include('livewire.activities.partials.manage-hosting-mode-tab')
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>

        <x-slot:actions class="px-6 pb-6">
            @if ($this->slug)
            <x-button id="ui-activity-cancel" :link="route('activities.show', ['activity' => $editingActivity->slug])" class="btn-outline ui-action ui-action-cancel" data-ui="activity-cancel">{{ __('ui.common.cancel') }}</x-button>
            @endif

            <x-button id="ui-activity-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="activity-submit" wire:loading.attr="disabled" wire:target="save" spinner="save">
                <span wire:loading.remove wire:target="save">
                    {{
                        ($editingActivityId && ($proposalFieldsReadonly ?? false)
                            ? __('ui.activities.update')
                            : ($hosting_mode === \App\Models\Activity::HOSTING_MODE_PROPOSED_TO_EVENT
                                ? __('ui.proposals.submit_proposal')
                                : ($editingActivityId
                                    ? __('ui.activities.update')
                                    : __('ui.activities.create')))
                        )
                    }}
                </span>
                <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
            </x-button>
        </x-slot:actions>
    </x-form>

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    />
</div>

@push('scripts')
<script>
(() => {
    let manageActivityFormScriptsAbort;
    function initManageActivityFormScripts() {
        manageActivityFormScriptsAbort?.abort();
        manageActivityFormScriptsAbort = new AbortController();
        const signal = manageActivityFormScriptsAbort.signal;

        const activityForm = document.querySelector('form[data-activity-form]');
        if (activityForm) {
            activityForm.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const t = e.target;
                if (t.closest('.tox-tinymce') || t.closest('.tox-toolbar')) return;
                if (t.tagName === 'TEXTAREA') return;
                if (t.tagName === 'BUTTON') return;
                if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) return;
                if (t.hasAttribute('data-ts-input')) return;
                if (t.hasAttribute('data-activity-name-input')) return;
                if (t.hasAttribute('data-proposal-event-input')) return;
                if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                    e.preventDefault();
                }
            }, { signal });
        }

        const activityTabsRoot = document.querySelector('[data-ui="activity-manage-tabs"]');
        const refreshSelfHostedMaps = () => {
            document.querySelectorAll('#ui-activity-selfhost-places-section[data-event-places-unified]').forEach((root) => {
                if (typeof root._epScheduleInvalidate === 'function') {
                    root._epScheduleInvalidate();
                } else {
                    window.dispatchEvent(new Event('resize'));
                }
            });
        };
        if (activityTabsRoot) {
            activityTabsRoot.addEventListener('click', (e) => {
                const tabButton = e.target.closest('[role="tab"]');
                if (!tabButton) {
                    return;
                }
                // Hosting tab can become visible asynchronously after Alpine/Livewire updates.
                [0, 120, 320, 700].forEach((ms) => {
                    setTimeout(refreshSelfHostedMaps, ms);
                });
            }, { signal });
        }

        const nameInput = document.querySelector('[data-activity-name-input]');
        const namePopup = document.querySelector('[data-activity-name-popup]');
        if (nameInput && namePopup) {
            const suggestions = @json($nameSuggestions);
            let shown = [];
            let active = -1;

            function closeNamePopup() {
                namePopup.classList.add('hidden');
                namePopup.innerHTML = '';
                active = -1;
                nameInput.setAttribute('aria-expanded', 'false');
            }

            function openNamePopup() {
                if (shown.length === 0) {
                    closeNamePopup();
                    return;
                }
                namePopup.classList.remove('hidden');
                nameInput.setAttribute('aria-expanded', 'true');
            }

            function applyActive() {
                [...namePopup.querySelectorAll('[data-suggestion-idx]')].forEach((el, idx) => {
                    const isActive = idx === active;
                    el.classList.toggle('bg-base-200', isActive);
                    el.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            }

            function choose(value) {
                nameInput.value = value;
                nameInput.dispatchEvent(new Event('input', { bubbles: true }));
                closeNamePopup();
            }

            function render(items) {
                shown = items.slice(0, 8);
                namePopup.innerHTML = '';
                active = -1;

                if (shown.length === 0) {
                    closeNamePopup();
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
                namePopup.appendChild(frag);
                openNamePopup();
            }

            function updateFromInput() {
                const q = nameInput.value.trim().toLowerCase();
                if (q.length < 1) {
                    const items = suggestions.slice(0, 8);
                    render(items);
                    return;
                }

                const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
                render(items);
            }

            nameInput.addEventListener('input', updateFromInput, { signal });
            nameInput.addEventListener('focus', updateFromInput, { signal });
            nameInput.addEventListener('keydown', (e) => {
                if (namePopup.classList.contains('hidden') || shown.length === 0) return;

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
                    closeNamePopup();
                }
            }, { signal });

            document.addEventListener('click', (e) => {
                if (!namePopup.contains(e.target) && e.target !== nameInput) {
                    closeNamePopup();
                }
            }, { signal });
        }

        activityFormBindSelfHostedRoomIfPresent();
    }

    /** Room autocomplete + ep:change use document delegation so Livewire morphs do not leave dead listeners. */
    const activityFormRoomStateByRoot = new WeakMap();

    function activityFormRoomGetState(roomRoot) {
        if (!activityFormRoomStateByRoot.has(roomRoot)) {
            activityFormRoomStateByRoot.set(roomRoot, { cache: new Map(), lastVenueKey: null, rooms: [], popupActive: -1 });
        }

        return activityFormRoomStateByRoot.get(roomRoot);
    }

    function activityFormRoomGetEls(form) {
        const roomRoot = form?.querySelector('[data-selfhost-room-root]');
        if (!roomRoot) {
            return null;
        }
        const mapWrap = form.querySelector('[data-selfhost-map-wrap]');
        const roomInput = roomRoot.querySelector('[data-selfhost-room-input]');
        const roomPopup = roomRoot.querySelector('[data-selfhost-room-popup]');
        const template = roomRoot.getAttribute('data-selfhost-rooms-url-template') || '';
        if (!mapWrap || !roomInput || !roomPopup) {
            return null;
        }

        return { roomRoot, mapWrap, roomInput, roomPopup, template };
    }

    function activityFormRoomGetSelectedVenueId(mapWrap) {
        const selected = mapWrap.querySelector('[data-ep-place-ids] input[name="place_ids[]"]');

        return selected && selected.value ? String(selected.value) : '';
    }

    function activityFormRoomHasNewVenueDraft(mapWrap) {
        return !!mapWrap.querySelector('[data-ep-new-venues] [data-ep-nv-id]');
    }

    function activityFormRoomLoadForVenue(state, template, venueId) {
        if (!venueId || !template) {
            state.rooms = [];

            return Promise.resolve();
        }
        if (state.cache.has(venueId)) {
            state.rooms = state.cache.get(venueId);

            return Promise.resolve();
        }
        const url = template.replace('__PLACE__', encodeURIComponent(venueId));

        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                const list = data && Array.isArray(data.rooms) ? data.rooms : [];
                const listNorm = list
                    .map((x) => ({ id: x.id, name: String(x.name ?? '') }))
                    .filter((x) => x.name !== '');
                state.cache.set(venueId, listNorm);
                state.rooms = listNorm;
            })
            .catch(() => {
                state.rooms = [];
            });
    }

    function activityFormRoomClose(roomPopup, roomInput) {
        roomPopup.classList.add('hidden');
        roomPopup.innerHTML = '';
        roomInput.setAttribute('aria-expanded', 'false');
    }

    function activityFormRoomSetEnabled(roomInput, enabled) {
        roomInput.disabled = !enabled;
    }

    function activityFormRoomRender(els, state, shownItems) {
        const { roomInput, roomPopup } = els;
        roomPopup.innerHTML = '';
        if (shownItems.length === 0) {
            activityFormRoomClose(roomPopup, roomInput);

            return;
        }
        if (state.popupActive < 0 || state.popupActive >= shownItems.length) {
            state.popupActive = -1;
        }
        const r = roomInput.getBoundingClientRect();
        roomPopup.style.left = `${r.left}px`;
        roomPopup.style.top = `${r.bottom + 4}px`;
        roomPopup.style.width = `${r.width}px`;
        const frag = document.createDocumentFragment();
        shownItems.forEach((it, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'block w-full px-3 py-2 text-left text-sm hover:bg-base-200'
                + (idx === state.popupActive ? ' bg-base-200' : '');
            btn.textContent = it.name;
            btn.dataset.suggestionIdx = String(idx);
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', 'false');
            btn.addEventListener('mousedown', (e) => e.preventDefault());
            btn.addEventListener('click', () => {
                roomInput.value = it.name;
                roomInput.dispatchEvent(new Event('input', { bubbles: true }));
                roomInput.dispatchEvent(new Event('blur', { bubbles: true }));
                activityFormRoomClose(roomPopup, roomInput);
            });
            frag.appendChild(btn);
        });
        roomPopup.appendChild(frag);
        roomPopup.classList.remove('hidden');
        roomInput.setAttribute('aria-expanded', 'true');
    }

    function activityFormRoomUpdatePopup(els, state) {
        const { roomInput, roomPopup } = els;
        state.popupActive = -1;
        const q = roomInput.value.trim().toLowerCase();
        const items =
            q.length < 1
                ? state.rooms
                : state.rooms.filter((r) => r.name.toLowerCase().includes(q) && r.name.toLowerCase() !== q);
        activityFormRoomRender(els, state, items.slice(0, 8));
    }

    function activityFormRoomSyncFromMap(els, detail) {
        const { roomRoot, mapWrap, roomInput, roomPopup, template } = els;
        const state = activityFormRoomGetState(roomRoot);
        const venueId = activityFormRoomGetSelectedVenueId(mapWrap);
        const newDraft =
            (detail && Array.isArray(detail.newVenues) && detail.newVenues.length > 0)
            || (detail === undefined && activityFormRoomHasNewVenueDraft(mapWrap));
        activityFormRoomSetEnabled(roomInput, !!venueId && !newDraft);
        const key = newDraft ? 'new' : venueId;
        const firstRun = roomRoot.dataset.activityRoomVenueInit !== '1';

        if (firstRun) {
            roomRoot.dataset.activityRoomVenueInit = '1';
            state.lastVenueKey = key;
            if (newDraft || !venueId) {
                state.rooms = [];
                activityFormRoomClose(roomPopup, roomInput);
            } else {
                activityFormRoomLoadForVenue(state, template, venueId).then(() => activityFormRoomClose(roomPopup, roomInput));
            }

            return;
        }

        if (key !== state.lastVenueKey) {
            state.lastVenueKey = key;
            if (newDraft || !venueId) {
                state.rooms = [];
                if (roomInput.value !== '') {
                    roomInput.value = '';
                    roomInput.dispatchEvent(new Event('blur', { bubbles: true }));
                }
                activityFormRoomClose(roomPopup, roomInput);

                return;
            }
            if (roomInput.value !== '') {
                roomInput.value = '';
                roomInput.dispatchEvent(new Event('blur', { bubbles: true }));
            }
            activityFormRoomLoadForVenue(state, template, venueId).then(() => activityFormRoomClose(roomPopup, roomInput));
        } else {
            activityFormRoomClose(roomPopup, roomInput);
        }
    }

    let activityFormRoomDelegationBound = false;

    function activityFormBindSelfHostedRoomIfPresent() {
        if (activityFormRoomDelegationBound) {
            activityFormRoomMaybeInitialSync();

            return;
        }
        activityFormRoomDelegationBound = true;

        document.addEventListener(
            'ep:change',
            (e) => {
                const unified = e.target.closest?.('[data-event-places-unified]');
                if (!unified) {
                    return;
                }
                const form = unified.closest('form[data-activity-form]');
                if (!form) {
                    return;
                }
                const els = activityFormRoomGetEls(form);
                if (!els) {
                    return;
                }
                activityFormRoomSyncFromMap(els, e.detail);
            },
            false,
        );

        document.addEventListener(
            'focusin',
            (e) => {
                const roomInput = e.target.closest?.('[data-selfhost-room-input]');
                if (!roomInput) {
                    return;
                }
                const form = roomInput.closest('form[data-activity-form]');
                if (!form) {
                    return;
                }
                const els = activityFormRoomGetEls(form);
                if (!els) {
                    return;
                }
                const { mapWrap, template, roomPopup } = els;
                const state = activityFormRoomGetState(els.roomRoot);
                const venueId = activityFormRoomGetSelectedVenueId(mapWrap);
                if (!venueId || activityFormRoomHasNewVenueDraft(mapWrap)) {
                    activityFormRoomClose(els.roomPopup, roomInput);

                    return;
                }
                activityFormRoomLoadForVenue(state, template, venueId).then(() => activityFormRoomUpdatePopup(els, state));
            },
            true,
        );

        document.addEventListener('input', (e) => {
            const roomInput = e.target.closest?.('[data-selfhost-room-input]');
            if (!roomInput) {
                return;
            }
            const form = roomInput.closest('form[data-activity-form]');
            const els = form ? activityFormRoomGetEls(form) : null;
            if (!els) {
                return;
            }
            const state = activityFormRoomGetState(els.roomRoot);
            activityFormRoomUpdatePopup(els, state);
        });

        document.addEventListener('keydown', (e) => {
            const roomInput = e.target.closest?.('[data-selfhost-room-input]');
            if (!roomInput) {
                return;
            }
            const form = roomInput.closest('form[data-activity-form]');
            const els = form ? activityFormRoomGetEls(form) : null;
            if (!els || els.roomPopup.classList.contains('hidden')) {
                return;
            }
            const options = els.roomPopup.querySelectorAll('[role="option"]');
            if (options.length === 0) {
                return;
            }
            const st = activityFormRoomGetState(els.roomRoot);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                st.popupActive = st.popupActive < 0 ? 0 : Math.min(st.popupActive + 1, options.length - 1);
                options.forEach((opt, i) => opt.classList.toggle('bg-base-200', i === st.popupActive));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                st.popupActive = st.popupActive < 0 ? options.length - 1 : Math.max(st.popupActive - 1, 0);
                options.forEach((opt, i) => opt.classList.toggle('bg-base-200', i === st.popupActive));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const pick = st.popupActive >= 0 ? st.popupActive : 0;
                options[pick]?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            } else if (e.key === 'Escape') {
                activityFormRoomClose(els.roomPopup, roomInput);
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest?.('[data-selfhost-room-input]') || e.target.closest?.('[data-selfhost-room-popup]')) {
                return;
            }
            document.querySelectorAll('[data-selfhost-room-popup]').forEach((p) => {
                const root = p.closest('[data-selfhost-room-root]');
                const inp = root?.querySelector('[data-selfhost-room-input]');
                if (inp) {
                    activityFormRoomClose(p, inp);
                }
            });
        });

        activityFormRoomMaybeInitialSync();
    }

    function activityFormRoomMaybeInitialSync() {
        const form = document.querySelector('form[data-activity-form]');
        const els = form ? activityFormRoomGetEls(form) : null;
        if (!els) {
            return;
        }
        activityFormRoomSyncFromMap(els, undefined);
    }

    let activityFormValidationScrollHooked = false;
    let activityFormSubmitAt = 0;
    let activityFormSubmitClearTimer = null;

    function activityFormRegisterValidationScrollHook() {
        if (activityFormValidationScrollHooked) {
            return;
        }
        if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
            return;
        }
        activityFormValidationScrollHooked = true;
        document.addEventListener(
            'submit',
            (e) => {
                if (e.target?.matches?.('form[data-activity-form]')) {
                    activityFormSubmitAt = Date.now();
                    clearTimeout(activityFormSubmitClearTimer);
                    activityFormSubmitClearTimer = setTimeout(() => {
                        activityFormSubmitAt = 0;
                    }, 5000);
                }
            },
            true,
        );
        window.Livewire.hook('morphed', () => {
            if (!activityFormSubmitAt) {
                return;
            }
            requestAnimationFrame(() => {
                if (!activityFormSubmitAt) {
                    return;
                }
                const form = document.querySelector('form[data-activity-form]');
                if (!form) {
                    return;
                }
                const err =
                    form.querySelector('ul.text-error')
                    || form.querySelector('fieldset .text-error')
                    || form.querySelector('.label.text-error')
                    || form.querySelector('[class*="input-error"]')
                    || form.querySelector('.text-error');
                if (err) {
                    err.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    clearTimeout(activityFormSubmitClearTimer);
                    activityFormSubmitAt = 0;
                }
            });
        });
    }

    document.addEventListener('livewire:init', activityFormRegisterValidationScrollHook);
    document.addEventListener('DOMContentLoaded', activityFormRegisterValidationScrollHook);
    window.addEventListener('load', activityFormRegisterValidationScrollHook);
    activityFormRegisterValidationScrollHook();

    document.addEventListener('livewire:navigating', () => {
        manageActivityFormScriptsAbort?.abort();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initManageActivityFormScripts, { once: true });
    } else {
        initManageActivityFormScripts();
    }
    document.addEventListener('livewire:navigated', initManageActivityFormScripts);
})();
</script>
@endpush
