<x-modal
    wire:model="participantsInfoModalOpen"
    :title="__('ui.activities.participants_info_modal_title')"
    class="backdrop-blur"
    box-class="ui-modal-surface"
    separator
    data-ui="activity-participants-info-modal"
>
    <p class="mb-3 text-sm text-base-content/70">{{ __('ui.activities.participants_info_modal_intro') }}</p>
    <div class="rich-text-content max-h-[60vh] overflow-y-auto text-justify text-base-content/90">
        {!! rich_text($activity->participants_only_description) !!}
    </div>
    <div class="modal-action">
        <x-button type="button" class="btn-primary" wire:click="$set('participantsInfoModalOpen', false)">
            {{ __('ui.activities.participants_info_modal_ack') }}
        </x-button>
    </div>
</x-modal>