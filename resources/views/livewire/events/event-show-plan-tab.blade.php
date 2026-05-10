<div
    wire:key="plan-{{ $slotListVersion }}-{{ $planCounterRefreshTick }}"
    data-ui="event-show-plan-tab-livewire"
>
    @include('livewire.events.partials.show-plan-tab')

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    >
        @if ($pendingAction === 'cancel_slot_activity' && $pendingContextId !== null)
            <div class="form-control">
                <label class="label">
                    <span class="label-text">{{ __('ui.activities.cancel_reason_label') }}</span>
                </label>
                <textarea
                    class="textarea textarea-bordered w-full"
                    rows="4"
                    wire:model.defer="slotCancelReason.{{ (int) $pendingContextId }}"
                ></textarea>
                @error('slotCancelReason.'.$pendingContextId)
                    <div class="mt-2 text-xs text-error">{{ $message }}</div>
                @enderror
            </div>
        @endif
    </x-ui.confirm-modal>
</div>
