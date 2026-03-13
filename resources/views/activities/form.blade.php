@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $activity->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="type" :value="__('Type')" />
            <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                @foreach (['rpg','board','card','larp','lecture','workshop','competition','show'] as $type)
                    <option value="{{ $type }}"
                        @selected(old('type', $activity->type ?? '') === $type)>
                        {{ ucfirst($type) }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="host_user_id" :value="__('Host (optional)')" />
            <x-text-input id="host_user_id" name="host_user_id" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('host_user_id', $activity->host_user_id ?? '') }}" />
            <x-input-error :messages="$errors->get('host_user_id')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">
                {{ __('For now enter user ID; later this will be a selector.') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="min_participants" :value="__('Min participants')" />
            <x-text-input id="min_participants" name="min_participants" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('min_participants', $activity->min_participants ?? '') }}" />
            <x-input-error :messages="$errors->get('min_participants')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="max_participants" :value="__('Max participants')" />
            <x-text-input id="max_participants" name="max_participants" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('max_participants', $activity->max_participants ?? '') }}" />
            <x-input-error :messages="$errors->get('max_participants')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="age_limit" :value="__('Age limit (min)')" />
            <x-text-input id="age_limit" name="age_limit" type="number" min="0" class="mt-1 block w-full"
                          value="{{ old('age_limit', $activity->age_limit ?? '') }}" />
            <x-input-error :messages="$errors->get('age_limit')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="duration_minutes" :value="__('Duration (minutes)')" />
            <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" class="mt-1 block w-full"
                          value="{{ old('duration_minutes', $activity->duration_minutes ?? '') }}" />
            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="price" :value="__('Price (optional)')" />
            <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                          value="{{ old('price', $activity->price ?? '') }}" />
            <x-input-error :messages="$errors->get('price')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="signoff_deadline_hours" :value="__('Signoff deadline (hours before start)')" />
            <x-text-input id="signoff_deadline_hours" name="signoff_deadline_hours" type="number" min="0" class="mt-1 block w-full"
                          value="{{ old('signoff_deadline_hours', $activity->signoff_deadline_hours ?? '') }}" />
            <x-input-error :messages="$errors->get('signoff_deadline_hours')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
            <input id="is_restricted" name="is_restricted" type="checkbox" value="1"
                   @checked(old('is_restricted', $activity->is_restricted ?? false)) />
            <x-input-label for="is_restricted" :value="__('Restricted (host approves waitlist)')" />
        </div>

        <div class="flex items-center gap-2">
            <input id="open_for_observers" name="open_for_observers" type="checkbox" value="1"
                   @checked(old('open_for_observers', $activity->open_for_observers ?? false)) />
            <x-input-label for="open_for_observers" :value="__('Open for observers')" />
        </div>
    </div>

    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                      value="{{ old('slug', $activity->slug ?? '') }}" required />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('activities.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('Cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('Save') }}
    </x-primary-button>
</div>

