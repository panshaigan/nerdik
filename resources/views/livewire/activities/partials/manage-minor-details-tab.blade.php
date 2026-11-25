<div class="space-y-6" data-ui="activity-manage-minor-details">
    <div>
        <div class="mb-2 flex items-center gap-2">
            <span class="text-sm font-semibold text-base-content">{{ __('ui.activities.participants_only_description') }}</span>
            <span
                class="tooltip tooltip-top inline-flex cursor-help"
                data-tip="{{ __('ui.activities.participants_only_description_help') }}"
            >
                <x-icon
                    name="o-question-mark-circle"
                    class="h-4 w-4 text-base-content/50"
                    :aria-label="__('ui.activities.participants_only_description_help')"
                />
            </span>
        </div>
        <x-editor
            id="activity-participants-only-description-editor"
            wire:model="participants_only_description"
            :gpl-license="true"
        />
        <x-field-error :messages="$errors->get('participants_only_description')" class="mt-2" />
    </div>

    @include('livewire.activities.partials.manage-suggested-bring-section')
</div>