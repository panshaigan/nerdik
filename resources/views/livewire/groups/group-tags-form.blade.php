<section data-ui="group-tags-form">
    <header class="mb-4">
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.groups.tags_title') }}
        </h2>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    @if ($canManage)
        <div class="space-y-3 overflow-visible" data-ui="group-tags-editor">
            <x-activity.category-tags-picker :config="$this->tagPickerConfig" />
            <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-end gap-3" data-ui="group-tags-save">
            <x-action-message class="me-3" on="group-tags-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button
                type="button"
                class="btn-primary"
                wire:click="save"
                spinner="save"
            >
                {{ __('ui.common.save') }}
            </x-button>
        </div>
    @elseif ($tagLabels !== [])
        <ul class="flex flex-wrap gap-2" data-ui="group-tags-readonly" role="list">
            @foreach ($tagLabels as $label)
                <li class="badge badge-outline">{{ $label }}</li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-base-content/60" data-ui="group-tags-empty">
            {{ __('ui.groups.tags_empty') }}
        </p>
    @endif
</section>
