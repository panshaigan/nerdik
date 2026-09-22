@props([
    'href' => null,
    'title',
    'subtitle' => null,
    'detail' => null,
    'imageUrl' => null,
    'icon' => null,
    'coverPicture' => null,
    'stacked' => false,
    'dataUi' => 'catalog-card',
])

@php
    /** @var \App\Support\Ui\ListingCardPicture|null $coverPicture */
    $hasCover = $coverPicture instanceof \App\Support\Ui\ListingCardPicture
        && $coverPicture->hasDisplayableImage();
    $tag = filled($href) ? 'a' : 'button';
    $stacked = (bool) $stacked;
@endphp

<{{ $tag }}
    @if (filled($href))
        href="{{ $href }}"
        wire:navigate
    @else
        type="button"
    @endif
    {{ $attributes->class([
        'ui-card ui-catalog-card ui-content-card card group relative flex h-full min-h-36 flex-col overflow-hidden p-5 no-underline sm:min-h-40 sm:p-6',
        'text-left' => ! filled($href) && ! $stacked,
        'text-center' => $stacked,
    ])->merge(['data-ui' => $dataUi]) }}
>
    @if ($hasCover)
        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden rounded-[inherit]" aria-hidden="true">
            <div class="absolute inset-0 scale-105">
                <x-listing-card-picture
                    :picture="$coverPicture"
                    class="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
            <div class="absolute inset-0 bg-base-100/85"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-base-100/80 via-base-100/40 to-base-100/25"></div>
        </div>
    @endif
    <div
        @class([
            'relative z-[1] flex min-w-0 flex-1 gap-4',
            'flex-col items-center' => $stacked,
            'items-center' => ! $stacked,
        ])
    >
        @if (filled($imageUrl))
            <img
                src="{{ $imageUrl }}"
                alt=""
                @class([
                    'size-16 shrink-0 object-cover sm:size-[4.5rem]',
                    'rounded-full' => $stacked,
                    'rounded-xl' => ! $stacked,
                ])
            />
        @elseif (filled($icon))
            <span
                class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-base-200/90 text-primary sm:size-[4.5rem]"
                aria-hidden="true"
            >
                <x-icon :name="$icon" class="h-8 w-8" />
            </span>
        @endif
        <div @class(['min-w-0', 'w-full' => $stacked, 'flex-1' => ! $stacked])>
            <h3 class="text-lg font-bold leading-snug text-neutral sm:text-xl">
                <span class="ui-link ui-link-title">{{ $title }}</span>
            </h3>
            @if (filled($subtitle))
                <p class="mt-1 text-sm text-base-content/70">{{ $subtitle }}</p>
            @endif
            @if (filled($detail))
                <p class="mt-0.5 text-sm text-base-content/60">{{ $detail }}</p>
            @endif
        </div>
    </div>
</{{ $tag }}>
