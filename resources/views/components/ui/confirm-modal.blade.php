@props([
    'title' => '',
    'message' => '',
    'confirmAction' => 'runConfirmedAction',
])

<x-modal {{ $attributes }} :title="$title" class="backdrop-blur" box-class="bg-texture-glass" persistent separator>
    <p class="text-sm text-base-content/80 whitespace-pre-line">{{ $message }}</p>

    @if (trim((string) $slot) !== '')
        <div class="mt-3">
            {{ $slot }}
        </div>
    @endif

    <div class="modal-action">
        <x-button type="button" class="btn-ghost" wire:click="$set('{{ $attributes->wire('model')->value() }}', false)">
            {{ __('ui.common.cancel') }}
        </x-button>
        <x-button type="button" class="btn-error" wire:click="{{ $confirmAction }}">
            {{ __('ui.common.confirm') }}
        </x-button>
    </div>
</x-modal>
