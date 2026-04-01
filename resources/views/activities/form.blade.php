@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="name" :value="__('ui.activities.name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $activity->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="type" :value="__('ui.activities.type')" />
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

        <div class="flex items-end">
            <div class="flex items-center gap-2 pb-0.5">
                <input type="hidden" name="creator_as_host" value="0" />
                <input id="creator_as_host" name="creator_as_host" type="checkbox" value="1"
                       @checked(old('creator_as_host', ($activity->exists ?? false) && (int) ($activity->host_user_id ?? 0) === (int) auth()->id() ? '1' : '0') === '1') />
                <x-input-label for="creator_as_host" :value="__('ui.activities.i_am_host')" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="min_participants" :value="__('ui.activities.min_participants')" />
            <x-text-input id="min_participants" name="min_participants" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('min_participants', $activity->min_participants ?? '') }}" />
            <x-input-error :messages="$errors->get('min_participants')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="max_participants" :value="__('ui.activities.max_participants')" />
            <x-text-input id="max_participants" name="max_participants" type="number" min="1" class="mt-1 block w-full"
                          value="{{ old('max_participants', $activity->max_participants ?? '') }}" />
            <x-input-error :messages="$errors->get('max_participants')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="age_limit" :value="__('ui.activities.age_limit')" />
            <x-text-input id="age_limit" name="age_limit" type="number" min="0" class="mt-1 block w-full"
                          value="{{ old('age_limit', $activity->age_limit ?? '') }}" />
            <x-input-error :messages="$errors->get('age_limit')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="duration_minutes" :value="__('ui.activities.duration_minutes')" />
            <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" class="mt-1 block w-full"
                          value="{{ old('duration_minutes', $activity->duration_minutes ?? '') }}" />
            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="signoff_deadline_hours" :value="__('ui.activities.signoff_deadline_hours')" />
        <x-text-input id="signoff_deadline_hours" name="signoff_deadline_hours" type="number" min="0" class="mt-1 block w-full max-w-md"
                      value="{{ old('signoff_deadline_hours', $activity->signoff_deadline_hours ?? '') }}" />
        <x-input-error :messages="$errors->get('signoff_deadline_hours')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
            <input id="is_restricted" name="is_restricted" type="checkbox" value="1"
                   @checked(old('is_restricted', $activity->is_restricted ?? false)) />
            <x-input-label for="is_restricted" :value="__('ui.activities.restricted')" />
        </div>

        <div class="flex items-center gap-2">
            <input id="open_for_observers" name="open_for_observers" type="checkbox" value="1"
                   @checked(old('open_for_observers', $activity->open_for_observers ?? false)) />
            <x-input-label for="open_for_observers" :value="__('ui.activities.open_for_observers')" />
        </div>
    </div>

    @if (isset($tags))
        <div class="border-t border-gray-200 pt-4 mt-4">
            <x-input-label :value="__('ui.activities.tags')" />
            <p class="text-xs text-gray-500 mb-3">{{ __('ui.activities.tags_help') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $activity->exists ? $activity->tags->pluck('id')->toArray() : []),
            ])
            <x-input-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-input-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('activities.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
        {{ __('ui.common.cancel') }}
    </a>

    <x-primary-button>
        {{ $submitLabel ?? __('ui.common.save') }}
    </x-primary-button>
</div>

