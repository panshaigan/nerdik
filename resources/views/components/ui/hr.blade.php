@props([
    'icon' => 'o-sparkles',
    'iconClass' => 'size-4',
    'leftEdgeIcon' => 'o-star',
    'rightEdgeIcon' => 'o-star',
    'edgeIconClass' => 'size-3 text-primary/80',
    'leftEdgeIconClass' => 'absolute left-1/2 -translate-x-12 w-4 h-4 text-primary/80',
    'rightEdgeIconClass' => 'absolute left-1/2 translate-x-8 w-4 h-4 text-primary/80',
    'wrapperClass' => 'flex items-center gap-4 px-2 -mt-3',
    'lineWrapClass' => 'relative flex-1 overflow-hidden rounded-full',
    'lineClass' => 'relative h-px',
    'leftLineClass' => 'bg-gradient-to-r from-transparent via-primary/75 to-primary/25',
    'rightLineClass' => 'bg-gradient-to-l from-transparent via-primary/75 to-primary/25',
    'lineGlowClass' => 'pointer-events-none absolute left-1/2 top-1/2 h-2 w-24 -translate-x-1/2 -translate-y-1/2 bg-primary/45 blur-md',
    'double' => false,
    'doubleGapClass' => 'space-y-1',
    'centerClass' => 'grid place-items-center rounded-full border border-primary/50 bg-base-100/70 text-primary shadow-[0_0_12px_theme(colors.primary/.80)]',
    'centerSizeClass' => 'size-8',
])

<div {{ $attributes->class([$wrapperClass]) }}>
    @php
        $showDoubleLine = filter_var($double, FILTER_VALIDATE_BOOLEAN);
        $lineCount = $showDoubleLine ? 2 : 1;
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

    <div class="{{ $centerClass }} {{ $centerSizeClass }}">
        <x-icon :name="$icon" :class="$iconClass" />
    </div>
    @if (filled((string) $rightEdgeIcon))
        <x-icon :name="$rightEdgeIcon" :class="trim($edgeIconClass.' '.$rightEdgeIconClass)" />
    @endif

    <div class="{{ $lineWrapClass }}">
        <div class="{{ $showDoubleLine ? $doubleGapClass : '' }}">
            @for ($i = 0; $i < $lineCount; $i++)
                <div class="{{ $lineClass }} {{ $rightLineClass }}">
                    <div class="{{ $lineGlowClass }}"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
