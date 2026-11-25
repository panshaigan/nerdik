<x-modal
    wire:model.preserve-scroll="participantsInfoModalOpen"
    :title="__('ui.activities.participants_info_modal_title')"
    box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
    class="backdrop-blur modal-bottom md:modal-end"
    separator
    data-ui="overlay-sheet activity-participants-info-modal"
>
    <div class="space-y-4" data-ui="activity-participants-info-modal-body">
        <p class="text-sm text-base-content/70">{{ __('ui.activities.participants_info_modal_intro') }}</p>
        <div class="rich-text-content max-h-[60vh] overflow-y-auto text-justify text-base-content/90">
            {!! rich_text($activity->participants_only_description) !!}
        </div>
    </div>

    <x-slot:actions>
        <x-button type="button" class="btn-primary" wire:click="$set('participantsInfoModalOpen', false)">
            {{ __('ui.activities.participants_info_modal_ack') }}
        </x-button>
    </x-slot:actions>
</x-modal>