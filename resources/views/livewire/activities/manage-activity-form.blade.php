@php
    use App\Enums\ActivityType;
    $activityTypes = ActivityType::values();
@endphp

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

<div class="space-y-4">
    <form wire:submit.prevent="save" class="space-y-4" data-activity-form>
        <div id="ui-activity-form-fields" class="ui-form ui-form-activity space-y-4" data-ui="activity-form-fields">
            <div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="relative sm:col-span-2">
                        <x-input
                            wire:model.live.debounce.300ms="name"
                            label="{{ __('ui.activities.name') }}"
                            type="text"
                            error-field="name"
                            required
                            autocomplete="off"
                            data-activity-name-input
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="activity-name-suggestions-popup"
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
                            id="type"
                            wire:model="type"
                            :label="__('ui.activities.type')"
                            error-field="type"
                            required
                            :options="collect($activityTypes)->map(fn ($t) => ['id' => $t, 'name' => ucfirst($t)])->values()->all()"
                            :placeholder="__('ui.activities.choose_type')"
                            placeholder-value=""
                        />
                    </div>
                </div>
            </div>

            <div>
                <x-editor
                    wire:model="description"
                    :label="__('ui.activities.description')"
                    :gpl-license="true"
                />
                <x-field-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="mt-4 border-t border-base-300 pt-4">
                <p class="fieldset-legend mb-0.5">{{ __('ui.activities.tags') }}</p>
                <p class="mb-2 text-xs text-base-content/70">{{ __('ui.activities.tags_help') }}</p>
                <div wire:ignore>
                    @include('tags.partials.selector', [
                        'tags' => $tags,
                        'selectedIds' => $tag_ids,
                    ])
                </div>
                <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
            </div>

            <div id="ui-activity-proposal-section" class="ui-proposal-section mt-4 border-t border-base-300 pt-4" data-ui="activity-proposal-section">
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-base-300 bg-base-100 p-3 flex h-full items-center">
                        <div class="space-y-4">
                            <x-checkbox
                                id="is_host_passive"
                                wire:model="is_host_passive"
                                :label="__('ui.activities.is_host_passive')"
                            />

                            <x-checkbox
                                id="requires_approval"
                                wire:model="requires_approval"
                                :label="__('ui.activities.requires_approval')"
                            />

                            <x-checkbox
                                id="allows_observers"
                                wire:model="allows_observers"
                                :label="__('ui.activities.allows_observers')"
                            />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-input
                                label="{{ __('ui.activities.min_participants') }}"
                                wire:model="min_participants"
                                type="number"
                                min="1"
                                data-activity-numeric
                                data-activity-participants="min"
                                error-field="min_participants"
                            >
                                <x-slot:append>
                                    <x-button
                                        type="button"
                                        class="btn-outline btn-xs"
                                        wire:click="$set('min_participants', null)"
                                        :aria-label="__('ui.activities.clear_field')"
                                    >×</x-button>
                                </x-slot:append>
                            </x-input>

                            <x-input
                                label="{{ __('ui.activities.max_participants') }}"
                                wire:model="max_participants"
                                type="number"
                                min="1"
                                data-activity-numeric
                                data-activity-participants="max"
                                error-field="max_participants"
                            >
                                <x-slot:append>
                                    <x-button
                                        type="button"
                                        class="btn-outline btn-xs"
                                        wire:click="$set('max_participants', null)"
                                        :aria-label="__('ui.activities.clear_field')"
                                    >×</x-button>
                                </x-slot:append>
                            </x-input>

                            <x-input
                                label="{{ __('ui.activities.minimum_age') }}"
                                wire:model="minimum_age"
                                type="number"
                                min="0"
                                data-activity-numeric
                                error-field="minimum_age"
                            >
                                <x-slot:append>
                                    <x-button
                                        type="button"
                                        class="btn-outline btn-xs"
                                        wire:click="$set('minimum_age', null)"
                                        :aria-label="__('ui.activities.clear_field')"
                                    >×</x-button>
                                </x-slot:append>
                            </x-input>

                            <x-input
                                label="{{ __('ui.activities.duration_in_minutes') }}"
                                wire:model="duration_in_minutes"
                                type="number"
                                min="0"
                                step="5"
                                data-activity-numeric
                                error-field="duration_in_minutes"
                            >
                                <x-slot:append>
                                    <x-button
                                        type="button"
                                        class="btn-outline btn-xs"
                                        wire:click="$set('duration_in_minutes', null)"
                                        :aria-label="__('ui.activities.clear_field')"
                                    >×</x-button>
                                </x-slot:append>
                            </x-input>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 md:items-center">
                <div class="h-full"></div>

                <div class="space-y-1">
                    <x-input
                        :label="__('ui.activities.cancellation_deadline_in_hours')"
                        :hint="__('ui.activities.cancellation_deadline_description')"
                        wire:model="cancellation_deadline_in_hours"
                        type="number"
                        min="0"
                        data-activity-numeric
                        class="w-full"
                        error-field="cancellation_deadline_in_hours"
                    >
                        <x-slot:append>
                            <x-button
                                type="button"
                                class="btn-outline btn-xs"
                                wire:click="$set('cancellation_deadline_in_hours', null)"
                                :aria-label="__('ui.activities.clear_field')"
                            >×</x-button>
                        </x-slot:append>
                    </x-input>
                </div>
            </div>
        </div>

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

        <div id="ui-activity-form-actions" class="ui-form-actions mt-6 flex justify-end gap-3" data-ui="activity-form-actions">
            <x-button id="ui-activity-clear-numeric" type="button" class="btn-outline ui-action ui-action-clear-numeric" wire:click="clearNumericFields" data-ui="activity-clear-numeric">
                {{ __('ui.activities.clear_numeric_fields') }}
            </x-button>

            <x-button id="ui-activity-cancel" :link="route('search.index')" class="btn-outline ui-action ui-action-cancel" data-ui="activity-cancel">{{ __('ui.common.cancel') }}</x-button>

            <x-button id="ui-activity-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="activity-submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $editingActivityId ? __('Update') : __('Create') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-button>
        </div>
    </form>
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

        function parseNum(el) {
            const v = el.value.trim();
            if (v === '') return null;
            const n = parseInt(v, 10);
            return Number.isFinite(n) ? n : null;
        }

        function syncActivityMinMax() {
            const form = document.querySelector('form[data-activity-form]');
            if (!form) return;
            const minEl = form.querySelector('input[data-activity-participants="min"]');
            const maxEl = form.querySelector('input[data-activity-participants="max"]');
            if (!minEl || !maxEl) return;

            const minV = parseNum(minEl);
            const maxV = parseNum(maxEl);

            if (minV !== null) {
                maxEl.min = String(minV);
            } else {
                maxEl.setAttribute('min', '1');
            }

            if (maxV !== null) {
                minEl.max = String(maxV);
            } else {
                minEl.removeAttribute('max');
            }

            if (minV !== null && maxV !== null && maxV < minV) {
                maxEl.value = String(minV);
                maxEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        const form = document.querySelector('form[data-activity-form]');
        const minEl = form?.querySelector('input[data-activity-participants="min"]');
        const maxEl = form?.querySelector('input[data-activity-participants="max"]');
        if (minEl && maxEl) {
            ['input', 'change'].forEach((ev) => {
                minEl.addEventListener(ev, syncActivityMinMax, { signal });
                maxEl.addEventListener(ev, syncActivityMinMax, { signal });
            });
            syncActivityMinMax();
            form?.addEventListener('submit', () => {
                syncActivityMinMax();
            }, { signal });
        }
    }

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
