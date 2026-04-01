@csrf

<div class="space-y-4">
    <div>
        <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.slots.event') }}</p>
        @if (isset($lockedEvent) && $lockedEvent)
            <input type="hidden" name="event_id" value="{{ $lockedEvent->id }}" />
            <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
            <p id="event_id" class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2 text-sm text-base-content">
                {{ $lockedEvent->name }} · {{ format_in_user_tz($lockedEvent->starts_at, 'Y-m-d H:i') }}
            </p>
            <p class="mt-1 text-xs text-base-content/60">{{ __('ui.slots.event_fixed') }}</p>
        @else
            <select id="event_id" name="event_id" class="select select-bordered mt-1 w-full" required>
                @foreach ($events as $ev)
                    <option value="{{ $ev->id }}"
                        @selected((string) old('event_id', $slot->event_id ?? '') === (string) $ev->id)>
                        {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                    </option>
                @endforeach
            </select>
        @endif
        <x-field-error :messages="$errors->get('event_id')" class="mt-2" />
    </div>

    <div>
        <x-input
            label="{{ __('ui.activities.name') }}"
            name="name"
            type="text"
            value="{{ old('name', $slot->name ?? '') }}"
            error-field="name"
            required
        />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input
                label="{{ __('ui.slots.starts_at_optional') }}"
                name="starts_at"
                type="datetime-local"
                value="{{ old('starts_at', $slot->starts_at ? format_in_user_tz($slot->starts_at, 'Y-m-d\TH:i') : '') }}"
                error-field="starts_at"
            />
        </div>

        <div>
            <x-input
                label="{{ __('ui.slots.ends_at_optional') }}"
                name="ends_at"
                type="datetime-local"
                value="{{ old('ends_at', $slot->ends_at ? format_in_user_tz($slot->ends_at, 'Y-m-d\TH:i') : '') }}"
                error-field="ends_at"
            />
        </div>
    </div>

    <div>
        <fieldset class="fieldset py-0">
            <legend class="fieldset-legend mb-0.5">{{ __('ui.slots.place_optional') }}</legend>
            <select id="place_id" name="place_id" class="select select-bordered w-full">
                <option value="">{{ __('ui.common.none') }}</option>
                @foreach ($places as $place)
                    <option value="{{ $place->id }}"
                        @selected((string) old('place_id', $slot->place_id ?? '') === (string) $place->id)>
                        {{ $place->name }} ({{ $place->type }})
                    </option>
                @endforeach
            </select>
        </fieldset>
        <x-field-error :messages="$errors->get('place_id')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="requires_approval" name="requires_approval" type="checkbox" value="1" class="checkbox checkbox-sm"
               @checked(old('requires_approval', $slot->requires_approval ?? false)) />
        <label for="requires_approval" class="label cursor-pointer text-sm text-base-content">{{ __('ui.slots.requires_approval') }}</label>
    </div>

    <div>
        <x-input
            label="{{ __('ui.slots.max_capacity_optional') }}"
            name="max_capacity"
            type="number"
            min="1"
            value="{{ old('max_capacity', $slot->max_capacity ?? '') }}"
            error-field="max_capacity"
        />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('slots.index') }}" class="btn btn-outline">
        {{ __('ui.common.cancel') }}
    </a>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('ui.common.save') }}</x-button>
</div>
