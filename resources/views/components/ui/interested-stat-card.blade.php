@props([
    'title',
    'value' => 0,
    'hasInterest' => false,
    'clickAddAction' => 'addInterest',
    'clickRemoveAction' => 'removeInterest',
    'target' => 'addInterest, removeInterest',
    'dataUi' => null,
])

@php
    $isAuthenticated = auth()->check();
@endphp

<div
    {{ $attributes->class([
        'box-glow-dark-primary rounded-2xl px-4 py-3',
        'relative overflow-hidden cursor-pointer select-none transition-transform duration-150 ease-out hover:box-glow-primary active:scale-[0.98]' => $isAuthenticated,
    ]) }}
    @if ($isAuthenticated)
        wire:click="{{ $hasInterest ? $clickRemoveAction : $clickAddAction }}"
        wire:loading.class.delay="pointer-events-none cursor-wait"
        wire:target="{{ $target }}"
    @endif
    @if ($dataUi)
        data-ui="{{ $dataUi }}"
    @endif
>
    <x-stat
        :title="$title"
        :value="$value"
        icon="{{ $hasInterest ? 's-star' : 'o-star' }}"
        color="{{ $isAuthenticated ? ($hasInterest ? 'text-warning' : 'text-base-content/80 hover:text-warning') : '' }}"
        class="ui-stat-embed"
    />
    @auth
        <div
            wire:loading.delay
            wire:target="{{ $target }}"
            class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-base-100/40"
            aria-live="polite"
        >
            <span class="loading loading-spinner loading-sm text-primary" aria-hidden="true"></span>
        </div>
    @endauth
</div>
