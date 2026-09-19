@props([
    'icon' => null,
    'href' => null,
    'external' => false,
])

@php
    $itemClass = 'flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-base-200';
@endphp

<li role="none">
    @if ($href)
        <a
            href="{{ $href }}"
            role="menuitem"
            @if ($external)
                target="_blank"
                rel="noopener noreferrer"
            @endif
            {{ $attributes->class($itemClass) }}
        >
            @if (filled($icon))
                <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            @endif
            <span>{{ $slot }}</span>
        </a>
    @else
        <button
            type="button"
            role="menuitem"
            {{ $attributes->class($itemClass) }}
        >
            @if (filled($icon))
                <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            @endif
            <span>{{ $slot }}</span>
        </button>
    @endif
</li>
