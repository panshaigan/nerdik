@props([
    'count' => 0,
])

@php
    $resolvedCount = (int) $count;
    $countLabel = $resolvedCount > 9 ? '9+' : (string) $resolvedCount;
@endphp

<span {{ $attributes->class('relative inline-flex')->merge(['data-count' => (string) $resolvedCount]) }}>
    {{ $slot }}
    @if ($resolvedCount > 0)
        <span class="pointer-events-none absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-black px-1 text-[10px] font-medium text-white">
            {{ $countLabel }}
        </span>
    @endif
</span>
