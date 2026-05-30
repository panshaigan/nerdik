@props([
    'target',
    'size' => 'loading-lg',
    'dataUi' => null,
])

<div
    wire:loading.delay
    wire:target="{{ $target }}"
    {{ $attributes->class([
        'pointer-events-none absolute inset-0 z-[15] flex items-center justify-center rounded-2xl bg-base-100/50 backdrop-blur-[1px]',
    ]) }}
    @if ($dataUi) data-ui="{{ $dataUi }}" @endif
    aria-live="polite"
    role="status"
>
    <span class="sr-only">{{ __('ui.common.loading') }}</span>
    <span class="loading loading-spinner {{ $size }} text-primary" aria-hidden="true"></span>
</div>
