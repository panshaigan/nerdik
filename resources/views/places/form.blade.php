@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $place->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" :value="__('Type')" />
        <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            @foreach (['country','state','city','venue','room'] as $type)
                <option value="{{ $type }}"
                    @selected(old('type', $place->type ?? '') === $type)>
                    {{ ucfirst($type) }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="parent_id" :value="__('Parent place (optional)')" />
        <select id="parent_id" name="parent_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}"
                    @selected((string) old('parent_id', $place->parent_id ?? '') === (string) $parent->id)>
                    {{ $parent->name }} ({{ $parent->type }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                      value="{{ old('slug', $place->slug ?? '') }}" required />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="links" :value="__('Links (optional)')" />
        <x-text-input id="links" name="links" type="text" class="mt-1 block w-full"
                      value="{{ old('links', $place->links ?? '') }}" />
        <x-input-error :messages="$errors->get('links')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="desc" :value="__('Description (optional)')" />
        <textarea id="desc" name="desc" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="3">{{ old('desc', $place->desc ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="is_online" name="is_online" type="checkbox" value="1"
               @checked(old('is_online', $place->is_online ?? false)) />
        <x-input-label for="is_online" :value="__('Is online place')" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="latitude" :value="__('Latitude (optional)')" />
            <x-text-input id="latitude" name="latitude" type="number" step="0.0000001" class="mt-1 block w-full"
                          value="{{ old('latitude', $place->latitude ?? '') }}" />
            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="longitude" :value="__('Longitude (optional)')" />
            <x-text-input id="longitude" name="longitude" type="number" step="0.0000001" class="mt-1 block w-full"
                          value="{{ old('longitude', $place->longitude ?? '') }}" />
            <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
        </div>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('places.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>

