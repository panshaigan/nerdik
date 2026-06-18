<div class="inline-flex">
    @if ($sendable)
        <x-button type="button" class="btn-sm btn-outline" wire:click="openModal">
            {{ $this->buttonLabel() }}
        </x-button>
    @endif

    @if ($modalOpen)
        <x-modal
            wire:model="modalOpen"
            :title="$this->buttonLabel()"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
        >
            <div class="space-y-4">
                <x-textarea
                    wire:model="message"
                    :label="__('ui.user_requests.optional_message')"
                    :placeholder="__('ui.user_requests.optional_message_placeholder')"
                    rows="3"
                />
            </div>

            <x-slot:actions>
                <x-button type="button" class="btn-ghost" wire:click="closeModal">{{ __('ui.common.cancel') }}</x-button>
                <x-button type="button" class="btn-primary" wire:click="send" wire:loading.attr="disabled">
                    {{ __('ui.user_requests.send_request') }}
                </x-button>
            </x-slot:actions>
        </x-modal>
    @endif
</div>
