@php
    $title = $editingActivityId ? (__('ui.activities.edit_activity').': '.$this->name) : __('ui.activities.create_activity');
@endphp
@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush
<div class="space-y-4">
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
        </div>
    </div>
    <x-form wire:submit.prevent="save" class="space-y-4 p-6" data-activity-form>
        <x-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" />
        <div id="ui-activity-form-fields" class="ui-form ui-form-activity space-y-4" data-ui="activity-form-fields">
            <div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="relative">
                        <x-input
                            wire:model.live.debounce.300ms="name"
                            label="{{ __('ui.activities.name') }}"
                            placeholder="{{ __('ui.activities.name') }}"
                            type="text"
                            error-field="name"
                            required
                            autocomplete="off"
                            data-activity-name-input
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="activity-name-suggestions-popup"
                            icon="o-bookmark"
                            inline
                        />
                        <div
                            id="activity-name-suggestions-popup"
                            class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                            data-activity-name-popup
                            role="listbox"
                            wire:ignore
                        ></div>
                    </div>

                    <div>
                        <x-select
                            id="activity_type_id"
                            wire:model="activity_type_id"
                            :label="__('ui.activities.type')"
                            error-field="activity_type_id"
                            required
                            :options="$activityTypes->map(fn ($type) => ['id' => $type->id, 'name' => __('ui.activities.types.'.$type->slug)])->values()->all()"
                            :placeholder="__('ui.activities.choose_type')"
                            placeholder-value=""
                            icon="o-squares-2x2"
                            inline
                        />
                    </div>

                    <div class="card border border-base-300 p-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div
                                x-data="{
                                    min: @entangle('min_participants'),
                                    max: @entangle('max_participants'),
                                    minLimit: 1,
                                    maxLimit: 20,

                                    get minPercent() {
                                        const val = Number(this.min ?? this.minLimit);
                                        const lo = this.minLimit;
                                        const hi = this.maxLimit;
                                        if (!Number.isFinite(val)) {
                                            return 0;
                                        }

                                        return ((val - lo) / (hi - lo)) * 100;
                                    },
                                    get maxPercent() {
                                        const val = Number(this.max ?? this.maxLimit);
                                        const lo = this.minLimit;
                                        const hi = this.maxLimit;
                                        if (!Number.isFinite(val)) {
                                            return 100;
                                        }

                                        return ((val - lo) / (hi - lo)) * 100;
                                    },

                                    init() {
                                        this.min = this.min ?? this.minLimit;
                                        this.max = this.max ?? this.maxLimit;

                                        this.$watch('min', v => {
                                            const val = Number(v);
                                            const maxVal = Number(this.max);
                                            if (val > maxVal) this.min = maxVal;
                                        });

                                        this.$watch('max', v => {
                                            const val = Number(v);
                                            const minVal = Number(this.min);
                                            if (val < minVal) this.max = minVal;
                                        });
                                    }
                                }"
                                class="space-y-1"
                            >
                                <!-- Label -->
                                <label class="text-sm font-medium flex justify-between">
                                    <span>{{ __('ui.activities.participants') }}</span>
                                    <span class="font-semibold" x-text="`${min}–${max}`"></span>
                                </label>

                                {{-- Dual range: Daisy .range / Mary <x-range> sizing; native fill off (.thumb-only); see .participants-dual-range in app.css --}}
                                <div class="participants-dual-range text-base-content">
                                    <div class="participants-dual-range-track" aria-hidden="true"></div>
                                    <div
                                        class="participants-dual-range-fill"
                                        aria-hidden="true"
                                        :style="{ left: minPercent + '%', width: Math.max(0, maxPercent - minPercent) + '%' }"
                                    ></div>

                                    <input
                                        type="range"
                                        x-model.number="min"
                                        :min="minLimit"
                                        :max="maxLimit"
                                        step="1"
                                        class="thumb-only range absolute top-1/2 left-0 z-20 w-full -translate-y-1/2"
                                        :class="min > (maxLimit / 2) ? 'z-30' : 'z-20'"
                                    >
                                    <input
                                        type="range"
                                        x-model.number="max"
                                        :min="minLimit"
                                        :max="maxLimit"
                                        step="1"
                                        class="thumb-only range absolute top-1/2 left-0 z-10 w-full -translate-y-1/2"
                                        :class="max <= (maxLimit / 2) ? 'z-30' : 'z-10'"
                                    >
                                </div>
                            </div>

                            <div
                                x-data="{ value: @entangle('minimum_age') }"
                                x-init="$nextTick(() => value = value ?? 0)"
                                class="space-y-1"
                            >
                                <label class="text-sm font-medium flex justify-between">
                                    <span>{{ __('ui.activities.minimum_age') }}: <span class="font-semibold" x-text="value"></span></span>
                                </label>
                                <x-range
                                    x-model="value"
                                    min="0"
                                    max="18"
                                />
                            </div>

                            <div x-data="{ value: @entangle('duration_in_minutes') }" class="space-y-1">
                                <label class="text-sm font-medium flex justify-between">
                                    <span>
                                        {{ __('ui.activities.duration_in_minutes') }}:
                                        <span class="font-semibold">
                                            <span x-text="Math.floor(value / 60)"></span>{{ __('ui.activities.duration_hours_short') }}
                                            <span x-show="value % 60 > 0">
                                                <span x-text="value % 60"></span>{{ __('ui.activities.duration_minutes_short') }}
                                            </span>
                                        </span>
                                    </span>
                                </label>
                                <x-range
                                    x-model="value"
                                    min="30"
                                    max="720"
                                    step="30"
                                />
                            </div>

                            <div x-data="{ value: @entangle('cancellation_deadline_in_hours') }" class="space-y-1">
                                <label class="text-sm font-medium flex justify-between">
                            <span>
                                {{ __('ui.activities.cancellation_deadline_in_hours') }}:
                                <span class="font-semibold">
                                    <span x-text="Math.floor(value / 24)" x-show="value >= 24"></span>
                                    <span x-text="Math.floor(value / 24) === 1 ? '{{ __('ui.activities.duration_day') }}' : '{{ __('ui.activities.duration_days') }}'" x-show="value >= 24"></span>
                                    <span x-show="value % 24 > 0">
                                        <span x-text="value % 24"></span>{{ __('ui.activities.duration_hours_short') }}
                                    </span>
                                </span>
                            </span>
                                    <x-popover class="transition-none">
                                        <x-slot:trigger>
                                            <x-icon name="o-information-circle" class="" :popover="__('ui.activities.cancellation_deadline_description')"/>
                                        </x-slot:trigger>
                                        <x-slot:content>
                                            {{ __('ui.activities.cancellation_deadline_description') }}
                                        </x-slot:content>
                                    </x-popover>

                                </label>
                                <x-range
                                    x-model="value"
                                    min="0"
                                    max="48"
                                    step="6"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="card border border-base-300 p-6 gap-y-3">
                        <x-toggle
                            id="requires_approval"
                            :label="__('ui.activities.requires_approval_badge')"
                            wire:model="requires_approval"
                            :hint="__('ui.activities.requires_approval')"
                            right
                        />
                        <x-toggle
                            id="allows_observers"
                            :label="__('ui.activities.allows_observers_badge')"
                            wire:model="allows_observers"
                            :hint="__('ui.activities.allows_observers')"
                            right
                        />
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <p class="fieldset-legend mb-0.5">{{ __('ui.activities.tags') }}</p>
                <x-activity.category-tags-picker :config="$activityTagPickerConfig" />
                <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
            </div>

            <div>
                <x-editor
                    wire:model="description"
                    :label="__('ui.activities.description')"
                    :gpl-license="true"
                    popover="dsadasd"
                />
                <x-field-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="mt-4 border-t border-base-300 pt-4 space-y-3">
                <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.activities.hosting_mode_label') }}</p>
                @if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                    <p class="text-sm text-base-content/70">{{ __('ui.activities.hosting_mode_locked_scheduled') }}</p>
                @else
                    <x-select
                        id="hosting_mode"
                        wire:model.live="hosting_mode"
                        :label="__('ui.activities.hosting_mode_label')"
                        error-field="hosting_mode"
                        :options="[
                            ['id' => \App\Models\Activity::HOSTING_MODE_DRAFT, 'name' => __('ui.activities.hosting_modes.draft')],
                            ['id' => \App\Models\Activity::HOSTING_MODE_SELF_HOSTED, 'name' => __('ui.activities.hosting_modes.self_hosted')],
                            ['id' => \App\Models\Activity::HOSTING_MODE_PROPOSED_TO_EVENT, 'name' => __('ui.activities.hosting_modes.proposed_to_event')],
                        ]"
                    />
                @endif

                @if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED)
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input
                            id="self_hosted_starts_at"
                            :label="__('ui.activities.self_hosted_starts_at')"
                            wire:model="self_hosted_starts_at"
                            type="datetime-local"
                            error-field="self_hosted_starts_at"
                        />
                    </div>

                    <div data-selfhost-map-wrap>
                        <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.self_hosted_place') }}</p>
                        <p class="mb-3 text-sm text-base-content/80">{{ __('ui.activities.self_hosted_place_help') }}</p>
                        <div id="ui-activity-selfhost-places-section" data-event-places-unified class="space-y-3" wire:ignore>
                            <script type="application/json" data-ep-config>@json($selfHostedPlacesConfig)</script>
                            <div class="relative z-[1000]">
                                <x-input
                                    type="search"
                                    data-ep-search
                                    autocomplete="off"
                                    :placeholder="__('ui.activities.self_hosted_place_search_placeholder')"
                                    class="w-full"
                                    :omit-error="true"
                                />
                                <div data-ep-results class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden max-h-60 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"></div>
                            </div>
                            <div data-ep-map class="z-0 w-full overflow-hidden rounded-md border border-base-300 bg-base-200/30" style="min-height: 280px; height: min(420px, 50vh);"></div>
                            <div data-ep-chips class="flex min-h-[1.5rem] flex-wrap gap-2"></div>
                            <div data-ep-new-venues-wrap class="{{ count($selfHostedPlacesConfig['initialNewPlaces'] ?? []) ? '' : 'hidden' }} space-y-2 rounded-lg border border-warning/30 bg-warning/5 p-3">
                                <p class="text-xs font-medium text-base-content" data-ep-new-heading>{{ __('ui.activities.self_hosted_new_venues_label') }}</p>
                                <div data-ep-new-venues class="space-y-3"></div>
                            </div>
                            <div data-ep-place-ids></div>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start"
                        data-selfhost-room-root
                        data-selfhost-rooms-url-template="{{ $roomsFetchUrlTemplate }}"
                    >
                        <div>
                            <p class="fieldset-legend mb-0.5">{{ __('ui.slots.room_optional') }}</p>
                            <div class="relative overflow-visible">
                                <x-input
                                    id="self_hosted_room_name"
                                    wire:model="self_hosted_room_name"
                                    :label="''"
                                    error-field="self_hosted_room_name"
                                    autocomplete="off"
                                    :placeholder="__('ui.slots.room_placeholder')"
                                    data-selfhost-room-input
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="selfhost-room-suggestions-popup"
                                />
                                <div
                                    id="selfhost-room-suggestions-popup"
                                    class="fixed z-[9999] hidden max-h-[min(14rem,50vh)] overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                                    data-selfhost-room-popup
                                    role="listbox"
                                ></div>
                            </div>
                            <x-field-error :messages="$errors->get('self_hosted_venue_place_id')" class="mt-2" />
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($hosting_mode === \App\Models\Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
        <div class="mt-4 border-t border-base-300 pt-4">
            <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.activities.propose_to_event') }}</p>
            <p class="mb-3 text-xs text-base-content/70">{{ __('ui.activities.propose_to_event_help') }}</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @php
                    $proposalEventSelectOptions = $futureEvents->map(function ($ev) {
                        $label = $ev->name;
                        if ($ev->starts_at) {
                            $label .= ' — '.format_in_user_tz($ev->starts_at, 'Y-m-d H:i');
                        }

                        return ['id' => $ev->id, 'name' => $label];
                    })->values()->all();
                @endphp
                <div>
                    <x-select
                        id="proposal_event_id"
                        wire:model.live="proposal_event_id"
                        :label="__('ui.activities.proposal_event')"
                        error-field="proposal_event_id"
                        class="ui-field ui-field-proposal-event"
                        data-ui="proposal-event-select"
                        :options="$proposalEventSelectOptions"
                        :placeholder="__('ui.activities.proposal_event_none')"
                        placeholder-value=""
                    />
                    @if ($futureEvents->isEmpty())
                        <p class="mt-1 text-xs text-base-content/60">{{ __('ui.activities.proposal_no_future_events') }}</p>
                    @endif
                </div>

                <div>
                    <x-input
                        id="proposal_preferred_start_time"
                        class="ui-field ui-field-proposal-preferred-time w-full"
                        :label="__('ui.activities.proposal_preferred_start_time')"
                        wire:model="proposal_preferred_start_time"
                        type="datetime-local"
                        error-field="proposal_preferred_start_time"
                        data-ui="proposal-preferred-time-input"
                    />
                </div>
            </div>

            @if ($proposal_event_id && $proposalEventSlots->isNotEmpty())
                <div class="mt-4 space-y-2 border-t border-base-300/50 pt-4">
                    <x-proposals.preferred-slot-checklist
                        :slots="$proposalEventSlots"
                        wire-model="proposal_slot_ids"
                        error-field="proposal_slot_ids"
                    />
                </div>
            @endif
        </div>
        @endif

        <x-slot:actions>
            <x-button id="ui-activity-cancel" :link="route('search.index')" class="btn-outline ui-action ui-action-cancel" data-ui="activity-cancel">{{ __('ui.common.cancel') }}</x-button>

            <x-button id="ui-activity-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="activity-submit" wire:loading.attr="disabled" wire:target="save" spinner="save">
                <span wire:loading.remove wire:target="save">{{ $editingActivityId ? __('ui.activities.update') : __('ui.activities.create') }}</span>
                <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
            </x-button>
        </x-slot:actions>
    </x-form>
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
                if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                    e.preventDefault();
                }
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
