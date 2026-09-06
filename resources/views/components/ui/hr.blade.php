@props([
    'color' => 'base-100',
    'icon' => 'o-sparkles',
    'iconClass' => 'size-4',
    'text' => null,
    'centerTextClass' => 'text-xs font-semibold tracking-tight whitespace-nowrap max-w-[14rem] truncate',
    'leftEdgeIcon' => 's-chevron-double-right',
    'rightEdgeIcon' => 's-chevron-double-left',
    'edgeIconClass' => 'size-3 text-[color-mix(in_oklab,var(--hr-color)_80%,transparent)]',
    'leftEdgeIconClass' => 'absolute left-1/2 -translate-x-10 w-4 h-4',
    'rightEdgeIconClass' => 'absolute left-1/2 translate-x-6 w-4 h-4',
    'wrapperClass' => 'flex items-center gap-4 px-2 -mt-3',
    'lineWrapClass' => 'relative flex-1 overflow-hidden rounded-full',
    'lineClass' => 'relative h-px',
    'leftLineClass' => 'bg-gradient-to-r from-transparent via-[color-mix(in_oklab,var(--hr-color)_75%,transparent)] to-[color-mix(in_oklab,var(--hr-color)_25%,transparent)]',
    'rightLineClass' => 'bg-gradient-to-l from-transparent via-[color-mix(in_oklab,var(--hr-color)_75%,transparent)] to-[color-mix(in_oklab,var(--hr-color)_25%,transparent)]',
    'lineGlowClass' => 'pointer-events-none absolute left-1/2 top-1/2 h-2 w-24 -translate-x-1/2 -translate-y-1/2 bg-[color-mix(in_oklab,var(--hr-color)_45%,transparent)] blur-md',
    'double' => false,
    'doubleGapClass' => 'space-y-1',
    'centerClass' => 'grid place-items-center rounded-full border border-[color-mix(in_oklab,var(--hr-color)_50%,transparent)] text-neutral shadow-[0_0_12px_color-mix(in_oklab,var(--hr-color)_80%,transparent)]',
    'centerSizeClass' => 'size-8',
    'centerTextWrapClass' => 'min-h-8 min-w-0 px-3 flex items-center justify-center text-center',
    'showEndGlow' => false,
    'endGlowClass' => 'pointer-events-none absolute right-[22%] top-1/2 size-1.5 -translate-y-1/2 rounded-full bg-[var(--hr-color)] shadow-[0_0_8px_var(--hr-color)]',
    'endGlowRightLineClass' => 'bg-[linear-gradient(to_left,transparent_0%,transparent_14%,color-mix(in_oklab,var(--hr-color)_70%,transparent)_20%,color-mix(in_oklab,var(--hr-color)_88%,transparent)_24%,color-mix(in_oklab,var(--hr-color)_16%,transparent)_34%,color-mix(in_oklab,var(--hr-color)_42%,transparent)_100%)]',
])

@php
    $colorToken = preg_match('/^[a-z][a-z0-9-]*$/', (string) $color) === 1 ? (string) $color : 'base-100';
    $showDoubleLine = filter_var($double, FILTER_VALIDATE_BOOLEAN);
    $lineCount = $showDoubleLine ? 2 : 1;
    $centerShowsText = filled($text);
    $showEndGlowDot = filter_var($showEndGlow, FILTER_VALIDATE_BOOLEAN);
@endphp

<div {{ $attributes->class([$wrapperClass])->merge(['style' => "--hr-color: var(--color-{$colorToken})"]) }}>
    <div class="{{ $lineWrapClass }}">
        <div class="{{ $showDoubleLine ? $doubleGapClass : '' }}">
            @for ($i = 0; $i < $lineCount; $i++)
                <div class="{{ $lineClass }} {{ $leftLineClass }}">
                    <div class="{{ $lineGlowClass }}"></div>
                </div>
            @endfor
        </div>
    </div>
    @if (filled((string) $leftEdgeIcon))
        <x-icon :name="$leftEdgeIcon" :class="trim($edgeIconClass.' '.$leftEdgeIconClass)" />
    @endif

    <div class="{{ $centerClass }} {{ $centerShowsText ? $centerTextWrapClass : $centerSizeClass }}">
        @if ($centerShowsText)
            <span class="{{ $centerTextClass }}">{{ $text }}</span>
        @else
            <x-icon :name="$icon" :class="$iconClass" />
        @endif
    </div>
    @if (filled((string) $rightEdgeIcon))
        <x-icon :name="$rightEdgeIcon" :class="trim($edgeIconClass.' '.$rightEdgeIconClass)" />
    @endif

    <div class="{{ $lineWrapClass }}">
        <div class="{{ $showDoubleLine ? $doubleGapClass : '' }}">
            @for ($i = 0; $i < $lineCount; $i++)
                <div class="{{ $lineClass }} {{ $showEndGlowDot ? $endGlowRightLineClass : $rightLineClass }}">
                    <div class="{{ $lineGlowClass }}"></div>
                    @if ($showEndGlowDot && $i === 0)
                        <div class="{{ $endGlowClass }}"></div>
                    @endif
                </div>
            @endfor
        </div>
    </div>
</div>
