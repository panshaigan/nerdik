@props([
    'title',
    'nameLabel',
    'descriptionLabel',
])

<x-modal
    wire:model="editSeriesModalOpen"
    :title="$title"
    box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
    class="backdrop-blur modal-bottom md:modal-end"
    separator
    data-ui="overlay-sheet"
>
    <div class="min-h-0 space-y-4 pt-2" data-ui="edit-series-form">
        <x-input
            wire:model="editSeriesName"
            :label="$nameLabel"
            type="text"
            maxlength="255"
            required
            inline
            data-ui="edit-series-name"
        />

        <x-textarea
            wire:model="editSeriesDescription"
            :label="$descriptionLabel"
            rows="5"
            data-ui="edit-series-description"
        />
    </div>

    <x-slot:actions>
        <div class="flex w-full flex-wrap items-center justify-end gap-2" data-overlay-sticky-footer>
            <x-button
                type="button"
                class="btn-ghost"
                wire:click="$set('editSeriesModalOpen', false)"
            >
                {{ __('ui.common.cancel') }}
            </x-button>
            <x-button
                type="button"
                class="btn-primary"
                wire:click="saveSeries"
                spinner="saveSeries"
                data-ui="edit-series-save"
            >
                {{ __('ui.common.save') }}
            </x-button>
        </div>
    </x-slot:actions>
</x-modal>
