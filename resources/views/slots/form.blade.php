@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="event_id" :value="__('Event')" />
        <select id="event_id" name="event_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            @foreach ($events as $ev)
                <option value="{{ $ev->id }}"
                    @selected((string) old('event_id', $slot->event_id ?? '') === (string) $ev->id)>
                    {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $slot->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts at (optional)')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('starts_at', $slot->starts_at ? format_in_user_tz($slot->starts_at, 'Y-m-d\TH:i') : '') }}" />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_at" :value="__('Ends at (optional)')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('ends_at', $slot->ends_at ? format_in_user_tz($slot->ends_at, 'Y-m-d\TH:i') : '') }}" />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="place_id" :value="__('Place (optional)')" />
        <select id="place_id" name="place_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($places as $place)
                <option value="{{ $place->id }}"
                    @selected((string) old('place_id', $slot->place_id ?? '') === (string) $place->id)>
                    {{ $place->name }} ({{ $place->type }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('place_id')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="requires_approval" name="requires_approval" type="checkbox" value="1"
               @checked(old('requires_approval', $slot->requires_approval ?? false)) />
        <x-input-label for="requires_approval" :value="__('Requires organizer approval')" />
    </div>

    <div>
        <x-input-label for="max_capacity" :value="__('Max capacity (optional)')" />
        <x-text-input id="max_capacity" name="max_capacity" type="number" min="1" class="mt-1 block w-full"
                      value="{{ old('max_capacity', $slot->max_capacity ?? '') }}" />
        <x-input-error :messages="$errors->get('max_capacity')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('slots.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>

