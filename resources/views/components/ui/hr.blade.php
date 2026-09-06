@props([
    'icon' => 'o-sparkles',
    'iconClass' => 'size-4',
    'text' => null,
    'centerTextClass' => 'text-xs font-semibold tracking-tight whitespace-nowrap max-w-[14rem] truncate',
    'leftEdgeIcon' => 's-chevron-double-right',
    'rightEdgeIcon' => 's-chevron-double-left',
    'edgeIconClass' => 'size-3 text-base-100/80',
    'leftEdgeIconClass' => 'absolute left-1/2 -translate-x-10 w-4 h-4 text-base-100/80',
    'rightEdgeIconClass' => 'absolute left-1/2 translate-x-6 w-4 h-4 text-base-100/80',
    'wrapperClass' => 'flex items-center gap-4 px-2 -mt-3',
    'lineWrapClass' => 'relative flex-1 overflow-hidden rounded-full',
    'lineClass' => 'relative h-px',
    'leftLineClass' => 'bg-gradient-to-r from-transparent via-base-100/75 to-base-100/25',
    'rightLineClass' => 'bg-gradient-to-l from-transparent via-base-100/75 to-base-100/25',
    'lineGlowClass' => 'pointer-events-none absolute left-1/2 top-1/2 h-2 w-24 -translate-x-1/2 -translate-y-1/2 bg-base-100/45 blur-md',
    'double' => false,
    'doubleGapClass' => 'space-y-1',
    'centerClass' => 'grid place-items-center rounded-full border border-base-100/50 bg-base-100/70 text-neutral shadow-[0_0_12px_theme(colors.base-100/.80)]',
    'centerSizeClass' => 'size-8',
    'centerTextWrapClass' => 'min-h-8 min-w-0 px-3 flex items-center justify-center text-center',
    'showEndGlow' => false,
    'endGlowClass' => 'pointer-events-none absolute right-[22%] top-1/2 size-1.5 -translate-y-1/2 rounded-full bg-base-100 shadow-[0_0_8px_theme(colors.base-100)]',
    'endGlowRightLineClass' => 'bg-[linear-gradient(to_left,transparent_0%,transparent_14%,color-mix(in_oklab,var(--color-base-100)_70%,transparent)_20%,color-mix(in_oklab,var(--color-base-100)_88%,transparent)_24%,color-mix(in_oklab,var(--color-base-100)_16%,transparent)_34%,color-mix(in_oklab,var(--color-base-100)_42%,transparent)_100%)]',
])

<div {{ $attributes->class([$wrapperClass]) }}>
    @php
        $showDoubleLine = filter_var($double, FILTER_VALIDATE_BOOLEAN);
        $lineCount = $showDoubleLine ? 2 : 1;
        $centerShowsText = filled($text);
        $showEndGlowDot = filter_var($showEndGlow, FILTER_VALIDATE_BOOLEAN);
    @endphp

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
