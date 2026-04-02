@php
    $embeddedInModal = $embeddedInModal ?? false;
    $slotModel = $slot ?? null;
    $activityTypes = \App\Http\Controllers\ActivityController::ACTIVITY_TYPES;
    $oldActivityTypes = old('activity_types', $slotModel?->activity_types ?? []);
    if (! is_array($oldActivityTypes)) {
        $oldActivityTypes = [];
    }
@endphp

<div class="space-y-4">
    <div>
        @if (isset($lockedEvent) && $lockedEvent)
            <input type="hidden" name="event_id" value="{{ $lockedEvent->id }}" />
        @else
            <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.slots.event') }}</p>
            <select id="event_id" name="event_id" class="select select-bordered mt-1 w-full" required>
                @foreach ($events as $ev)
                    <option value="{{ $ev->id }}"
                        @selected((string) old('event_id', $slotModel?->event_id ?? '') === (string) $ev->id)>
                        {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                    </option>
                @endforeach
            </select>
        @endif
        <x-field-error :messages="$errors->get('event_id')" class="mt-2" />
    </div>

    <div class="relative">
        <x-input
            label="{{ __('ui.activities.name') }}"
            name="name"
            type="text"
            value="{{ old('name', $slotModel?->name ?? '') }}"
            error-field="name"
            required
            autocomplete="off"
            data-slot-name-input
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="slot-edit-name-suggestions-popup"
        />
        <div
            id="slot-edit-name-suggestions-popup"
            class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
            data-slot-name-popup
            role="listbox"
        ></div>
        <script type="application/json" data-slot-name-suggestions-json="1">@json($slotNameSuggestions ?? [])</script>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input
                label="{{ __('ui.slots.starts_at_optional') }}"
                name="starts_at"
                type="datetime-local"
                value="{{ old('starts_at', $slotModel?->starts_at ? format_in_user_tz($slotModel->starts_at, 'Y-m-d\TH:i') : '') }}"
                error-field="starts_at"
            />
        </div>

        <div>
            <x-input
                label="{{ __('ui.slots.ends_at_optional') }}"
                name="ends_at"
                type="datetime-local"
                value="{{ old('ends_at', $slotModel?->ends_at ? format_in_user_tz($slotModel->ends_at, 'Y-m-d\TH:i') : '') }}"
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
                'selectedIds' => old('tag_ids', ($slotModel && $slotModel->exists) ? $slotModel->tags->pluck('id')->toArray() : []),
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
                        @selected((string) old('place_id', $slotModel?->place_id ?? '') === (string) $place->id)>
                        {{ $place->name }} ({{ $place->type }})
                    </option>
                @endforeach
            </select>
        </fieldset>
        <x-field-error :messages="$errors->get('place_id')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="requires_approval" name="requires_approval" type="checkbox" value="1" class="checkbox checkbox-sm"
               @checked(old('requires_approval', $slotModel?->requires_approval ?? false)) />
        <label for="requires_approval" class="label cursor-pointer text-sm text-base-content">{{ __('ui.slots.requires_approval') }}</label>
    </div>

    <div>
        <x-input
            label="{{ __('ui.slots.max_capacity_optional') }}"
            name="max_capacity"
            type="number"
            min="1"
            value="{{ old('max_capacity', $slotModel?->max_capacity ?? '') }}"
            error-field="max_capacity"
        />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    @if ($embeddedInModal)
        <button type="button" class="btn btn-outline" onclick="document.getElementById('slot-edit-modal')?.close()">
            {{ __('ui.common.cancel') }}
        </button>
    @else
        <a href="{{ route('slots.index') }}" class="btn btn-outline">
            {{ __('ui.common.cancel') }}
        </a>
    @endif

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('ui.common.save') }}</x-button>
</div>
