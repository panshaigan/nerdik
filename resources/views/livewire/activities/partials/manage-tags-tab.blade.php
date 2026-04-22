<div>
    <x-activity.category-tags-picker :config="$activityTagPickerConfig" />
    <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
    <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
</div>
