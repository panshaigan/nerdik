@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $event->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="organization_id" :value="__('Organization (optional)')" />
        <select id="organization_id" name="organization_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="">{{ __('None') }}</option>
            @foreach ($organizations as $organization)
                <option value="{{ $organization->id }}"
                    @selected((string) old('organization_id', $event->organization_id ?? '') === (string) $organization->id)>
                    {{ $organization->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('organization_id')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts at')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('starts_at', $event->starts_at ? format_in_user_tz($event->starts_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="ends_at" :value="__('Ends at')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full"
                          value="{{ old('ends_at', $event->ends_at ? format_in_user_tz($event->ends_at, 'Y-m-d\TH:i') : '') }}" required />
            <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="desc" :value="__('Description (optional)')" />
        <textarea id="desc" name="desc" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="3">{{ old('desc', $event->desc ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="flex items-center gap-2">
        <input id="is_public" name="is_public" type="checkbox" value="1"
               @checked(old('is_public', $event->is_public ?? true)) />
        <x-input-label for="is_public" :value="__('Public event')" />
    </div>

    @if (isset($tags) && $tags->isNotEmpty())
        <div class="border-t border-gray-200 pt-4 mt-4">
            <x-input-label :value="__('Tags')" />
            <p class="text-xs text-gray-500 mb-3">{{ __('Select tags that describe this event (games, themes, etc.).') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $event->exists ? $event->tags->pluck('id')->toArray() : []),
            ])
            <x-input-error :messages="$errors->get('tag_ids')" class="mt-2" />
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('events.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>
