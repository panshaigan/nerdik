@props([
    'title' => '',
    'message' => '',
    'confirmAction' => 'runConfirmedAction',
])

<x-modal {{ $attributes }} :title="$title">
    <p class="text-sm text-base-content/80 whitespace-pre-line">{{ $message }}</p>

    <div class="modal-action">
        <x-button type="button" class="btn-ghost" wire:click="$set('{{ $attributes->wire('model')->value() }}', false)">
            {{ __('ui.common.cancel') }}
        </x-button>
        <x-button type="button" class="btn-error" wire:click="{{ $confirmAction }}">
            {{ __('ui.common.confirm') }}
        </x-button>
    </div>
</x-modal>
