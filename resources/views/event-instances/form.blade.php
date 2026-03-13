@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="event_id" :value="__('Event')" />
        <select id="event_id" name="event_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            @foreach ($events as $event)
                <option value="{{ $event->id }}"
                    @selected((string) old('event_id', $instance->event_id ?? '') === (string) $event->id)>
                    {{ $event->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Name (optional)')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $instance->name ?? '') }}" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts at')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('starts_at', optional($instance->starts_at)->format('Y-m-d\TH:i') ?? '') }}" required />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_at" :value="__('Ends at')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('ends_at', optional($instance->ends_at)->format('Y-m-d\TH:i') ?? '') }}" required />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                      value="{{ old('slug', $instance->slug ?? '') }}" required />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="desc" :value="__('Description (optional)')" />
        <textarea id="desc" name="desc" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="3">{{ old('desc', $instance->desc ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('desc')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('event-instances.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>

