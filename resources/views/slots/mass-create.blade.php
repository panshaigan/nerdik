<form method="POST" action="{{ route('slots.store') }}">
    @csrf

    <input type="hidden" name="mass" value="1">

    <div class="space-y-4">
        <div>
            <x-input-label for="event_id" :value="__('Event')" />
            @if (isset($lockedEvent) && $lockedEvent)
                <input type="hidden" name="event_id" value="{{ $lockedEvent->id }}" />
                <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
                <p id="event_id" class="mt-1 text-sm text-gray-900 border border-gray-200 rounded-md px-3 py-2 bg-gray-50">
                    {{ $lockedEvent->name }} · {{ format_in_user_tz($lockedEvent->starts_at, 'Y-m-d H:i') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">{{ __('Event is fixed for this page.') }}</p>
            @else
                <select id="event_id" name="event_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}"
                            @selected((string) old('event_id', '') === (string) $ev->id)>
                            {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                        </option>
                    @endforeach
                </select>
            @endif
            <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="base_name" :value="__('Base name')" />
                <x-text-input id="base_name" name="base_name" type="text" class="mt-1 block w-full"
                              value="{{ old('base_name', 'Table') }}" required />
                <x-input-error :messages="$errors->get('base_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="count" :value="__('Count')" />
                <x-text-input id="count" name="count" type="number" min="1" max="100" class="mt-1 block w-full"
                              value="{{ old('count', 5) }}" required />
                <x-input-error :messages="$errors->get('count')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="starts_at" :value="__('Start time (optional)')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('starts_at') }}" />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="place_id" :value="__('Place (optional)')" />
            <select id="place_id" name="place_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="">{{ __('None') }}</option>
                @foreach ($places as $place)
                    <option value="{{ $place->id }}"
                        @selected((string) old('place_id') === (string) $place->id)>
                        {{ $place->name }} ({{ $place->type }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('place_id')" class="mt-2" />
        </div>

        <div class="flex items-center gap-2">
            <input id="requires_approval" name="requires_approval" type="checkbox" value="1"
                   @checked(old('requires_approval', false)) />
            <x-input-label for="requires_approval" :value="__('Requires organizer approval')" />
        </div>

        <div>
            <x-input-label for="max_capacity" :value="__('Max capacity (optional)')" />
            <x-text-input id="max_capacity" name="max_capacity" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('max_capacity') }}" />
            <x-input-error :messages="$errors->get('max_capacity')" class="mt-2" />
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('slots.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            {{ __('Cancel') }}
        </a>

        <x-primary-button>
            {{ __('Create slots') }}
        </x-primary-button>
    </div>
</form>
