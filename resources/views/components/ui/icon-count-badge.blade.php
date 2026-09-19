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
        <span class="pointer-events-none absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-black text-[10px] font-medium text-white">
            {{ $countLabel }}
        </span>
    @endif
</span>
