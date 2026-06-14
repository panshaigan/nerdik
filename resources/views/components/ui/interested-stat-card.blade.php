@props([
    'title',
    'value' => 0,
    'hasInterest' => false,
    'clickAddAction' => 'addInterest',
    'clickRemoveAction' => 'removeInterest',
    'target' => 'addInterest, removeInterest',
    'dataUi' => null,
    'icon' => null,
    'iconColor' => null,
])

@php
    $isAuthenticated = auth()->check();
@endphp

<div
    {{ $attributes->class([
        'rounded-2xl',
        'relative overflow-hidden cursor-pointer select-none transition-transform duration-150 ease-out active:scale-[0.98]' => $isAuthenticated,
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
        icon="{{ $icon ?? ($hasInterest ? 's-star' : 'o-star') }}"
        color="{{ $iconColor ?? ($isAuthenticated ? ($hasInterest ? 'text-warning' : 'text-base-content/80 group-hover:text-warning') : '') }}"
        class="ui-stat-embed ui-activity-show-stat"
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
