@php
    $embeddedInModal = $embeddedInModal ?? false;
    $activityTypes = \App\Http\Controllers\ActivityController::ACTIVITY_TYPES;
    $oldActivityTypes = old('activity_types', []);
    if (! is_array($oldActivityTypes)) {
        $oldActivityTypes = [];
    }
@endphp

<form method="POST" action="{{ route('slots.store') }}" data-slot-mass-form>
    @csrf

    <input type="hidden" name="mass" value="1">

    <div class="space-y-4">
        @if (isset($lockedEvent) && $lockedEvent)
            <input type="hidden" name="event_id" value="{{ $lockedEvent->id }}" />
            <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
        @else
            <div>
                <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.slots.event') }}</p>
                <select id="event_id" name="event_id" class="select select-bordered mt-1 w-full" required>
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}"
                            @selected((string) old('event_id', '') === (string) $ev->id)>
                            {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                        </option>
                    @endforeach
                </select>
                <x-field-error :messages="$errors->get('event_id')" class="mt-2" />
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="relative sm:col-span-1">
                <x-input
                    label="{{ __('ui.slots.base_name') }}"
                    name="base_name"
                    type="text"
                    value="{{ old('base_name') }}"
                    error-field="base_name"
                    required
                    autocomplete="off"
                    data-slot-base-name-input
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="slot-base-name-suggestions-popup"
                />
                <div
                    id="slot-base-name-suggestions-popup"
                    class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                    data-slot-base-name-popup
                    role="listbox"
                ></div>
            </div>

            <div>
                <x-input
                    label="{{ __('ui.slots.count') }}"
                    name="count"
                    type="number"
                    min="1"
                    max="100"
                    value="{{ old('count', 5) }}"
                    error-field="count"
                    required
                />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input
                    label="{{ __('ui.slots.starts_at_optional') }}"
                    name="starts_at"
                    type="datetime-local"
                    value="{{ old('starts_at') }}"
                    error-field="starts_at"
                />
            </div>
            <div>
                <x-input
                    label="{{ __('ui.slots.ends_at_optional') }}"
                    name="ends_at"
                    type="datetime-local"
                    value="{{ old('ends_at') }}"
                    error-field="ends_at"
                />
            </div>
        </div>

        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-1 font-medium">{{ __('ui.slots.activity_types') }}</legend>
                <p class="mb-1 text-xs text-base-content/70">{{ __('ui.slots.activity_types_help') }}</p>
                <p class="mb-2 text-xs text-base-content/50">{{ __('ui.slots.activity_types_multiselect_hint') }}</p>
                <select
                    name="activity_types[]"
                    multiple
                    class="select select-bordered min-h-[6rem] w-full py-2"
                    size="8"
                >
                    @foreach ($activityTypes as $type)
                        <option value="{{ $type }}" @selected(in_array($type, $oldActivityTypes, true))>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </fieldset>
            <x-field-error :messages="$errors->get('activity_types')" class="mt-2" />
            <x-field-error :messages="$errors->get('activity_types.*')" class="mt-2" />
        </div>

        @isset($tags)
            <div class="border-t border-base-300 pt-4">
                <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.tags') }}</p>
                <p class="mb-3 text-xs text-base-content/70">{{ __('ui.activities.tags_help') }}</p>
                @include('tags.partials.selector', [
                    'tags' => $tags,
                    'selectedIds' => old('tag_ids', []),
                ])
                <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
                <x-field-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
            </div>
        @endisset

        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5">{{ __('ui.slots.place_optional') }}</legend>
                <select id="place_id" name="place_id" class="select select-bordered w-full">
                    <option value="">{{ __('ui.common.none') }}</option>
                    @foreach ($places as $place)
                        <option value="{{ $place->id }}"
                            @selected((string) old('place_id') === (string) $place->id)>
                            {{ $place->name }} ({{ $place->type }})
                        </option>
                    @endforeach
                </select>
            </fieldset>
            <x-field-error :messages="$errors->get('place_id')" class="mt-2" />
        </div>

        <div class="flex items-center gap-2">
            <input id="requires_approval" name="requires_approval" type="checkbox" value="1" class="checkbox checkbox-sm"
                   @checked(old('requires_approval', false)) />
            <label for="requires_approval" class="label cursor-pointer text-sm text-base-content">{{ __('ui.slots.requires_approval') }}</label>
        </div>

        <div>
            <x-input
                label="{{ __('ui.slots.max_capacity_optional') }}"
                name="max_capacity"
                type="number"
                min="1"
                value="{{ old('max_capacity') }}"
                error-field="max_capacity"
            />
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        @if ($embeddedInModal)
            <button type="button" class="btn btn-outline" onclick="this.closest('dialog')?.close()">
                {{ __('ui.common.cancel') }}
            </button>
        @else
            <a href="{{ route('slots.index') }}" class="btn btn-outline">
                {{ __('ui.common.cancel') }}
            </a>
        @endif

        <x-button class="btn-primary" type="submit">{{ __('ui.slots.create_slots') }}</x-button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const massForm = document.querySelector('form[data-slot-mass-form]');
        if (massForm) {
            massForm.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const t = e.target;
                if (t.tagName === 'TEXTAREA') return;
                if (t.tagName === 'BUTTON') return;
                if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) return;
                if (t.hasAttribute('data-ts-input')) return;
                if (t.hasAttribute('data-slot-base-name-input')) return;
                if (t.tagName === 'SELECT' && t.multiple) return;
                if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                    e.preventDefault();
                }
            });
        }

        const nameInput = massForm?.querySelector('[data-slot-base-name-input]');
        const namePopup = massForm?.querySelector('[data-slot-base-name-popup]');
        if (nameInput && namePopup) {
            const suggestions = @json($slotNameSuggestions ?? []);
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
                    closeNamePopup();
                    return;
                }

                const items = suggestions.filter((s) => s.toLowerCase().includes(q) && s.toLowerCase() !== q);
                render(items);
            }

            nameInput.addEventListener('input', updateFromInput);
            nameInput.addEventListener('focus', updateFromInput);
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
            });

            document.addEventListener('click', (e) => {
                if (!namePopup.contains(e.target) && e.target !== nameInput) {
                    closeNamePopup();
                }
            });
        }
    });
</script>
