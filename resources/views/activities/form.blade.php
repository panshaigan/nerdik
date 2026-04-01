@csrf

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('ui.activities.name') }}"
            name="name"
            type="text"
            value="{{ old('name', $activity->name ?? '') }}"
            error-field="name"
            required
        />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5">{{ __('ui.activities.type') }}</legend>
                <select id="type" name="type" class="select select-bordered w-full" required>
                    @foreach (['rpg','board','card','larp','lecture','workshop','competition','show'] as $type)
                        <option value="{{ $type }}"
                            @selected(old('type', $activity->type ?? '') === $type)>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </fieldset>
            <x-field-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div class="flex items-end">
            <div class="flex items-center gap-2 pb-0.5">
                <input type="hidden" name="creator_as_host" value="0" />
                <input id="creator_as_host" name="creator_as_host" type="checkbox" value="1" class="checkbox checkbox-sm"
                       @checked(old('creator_as_host', ($activity->exists ?? false) && (int) ($activity->host_user_id ?? 0) === (int) auth()->id() ? '1' : '0') === '1') />
                <label for="creator_as_host" class="label cursor-pointer text-sm text-base-content">{{ __('ui.activities.i_am_host') }}</label>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input
                label="{{ __('ui.activities.min_participants') }}"
                name="min_participants"
                type="number"
                min="1"
                value="{{ old('min_participants', $activity->min_participants ?? '') }}"
                error-field="min_participants"
            />
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.max_participants') }}"
                name="max_participants"
                type="number"
                min="1"
                value="{{ old('max_participants', $activity->max_participants ?? '') }}"
                error-field="max_participants"
            />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input
                label="{{ __('ui.activities.age_limit') }}"
                name="age_limit"
                type="number"
                min="0"
                value="{{ old('age_limit', $activity->age_limit ?? '') }}"
                error-field="age_limit"
            />
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.duration_minutes') }}"
                name="duration_minutes"
                type="number"
                min="0"
                value="{{ old('duration_minutes', $activity->duration_minutes ?? '') }}"
                error-field="duration_minutes"
            />
        </div>
    </div>

    <div>
        <x-input
            label="{{ __('ui.activities.signoff_deadline_hours') }}"
            name="signoff_deadline_hours"
            type="number"
            min="0"
            class="max-w-md"
            value="{{ old('signoff_deadline_hours', $activity->signoff_deadline_hours ?? '') }}"
            error-field="signoff_deadline_hours"
        />
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <input id="is_restricted" name="is_restricted" type="checkbox" value="1" class="checkbox checkbox-sm"
                   @checked(old('is_restricted', $activity->is_restricted ?? false)) />
            <label for="is_restricted" class="label cursor-pointer text-sm text-base-content">{{ __('ui.activities.restricted') }}</label>
        </div>

        <div class="flex items-center gap-2">
            <input id="open_for_observers" name="open_for_observers" type="checkbox" value="1" class="checkbox checkbox-sm"
                   @checked(old('open_for_observers', $activity->open_for_observers ?? false)) />
            <label for="open_for_observers" class="label cursor-pointer text-sm text-base-content">{{ __('ui.activities.open_for_observers') }}</label>
        </div>
    </div>

    @if (isset($tags))
        <div class="mt-4 border-t border-base-300 pt-4">
            <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.tags') }}</p>
            <p class="mb-3 text-xs text-base-content/70">{{ __('ui.activities.tags_help') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $activity->exists ? $activity->tags->pluck('id')->toArray() : []),
            ])
            <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('activities.index') }}" class="btn btn-outline">
        {{ __('ui.common.cancel') }}
    </a>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('ui.common.save') }}</x-button>
</div>
