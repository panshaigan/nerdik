@props([
    'href' => null,
    'title',
    'subtitle' => null,
    'detail' => null,
    'imageUrl' => null,
    'icon' => null,
    'coverPicture' => null,
    'stacked' => false,
    'transparent' => false,
    'dataUi' => 'catalog-card',
    'editUrl' => null,
    'editAriaLabel' => null,
    'editDataUi' => null,
])

@php
    /** @var \App\Support\Ui\ListingCardPicture|null $coverPicture */
    $hasCover = $coverPicture instanceof \App\Support\Ui\ListingCardPicture
        && $coverPicture->hasDisplayableImage();
    $tag = filled($href) ? 'a' : 'button';
    $stacked = (bool) $stacked;
    $transparent = (bool) $transparent;
    $hasEdit = filled($editUrl);
@endphp

<div class="relative h-full min-w-0">
    <{{ $tag }}
        @if (filled($href))
            href="{{ $href }}"
            wire:navigate
        @else
            type="button"
        @endif
        {{ $attributes->class([
            'ui-card ui-catalog-card card group relative flex h-full min-h-36 w-full cursor-pointer flex-col overflow-hidden p-5 no-underline transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/60 hover:shadow-lg hover:shadow-primary/15 motion-reduce:hover:translate-y-0 sm:min-h-40 sm:p-6',
            'ui-content-card' => ! $transparent,
            'ui-tile-active' => $transparent,
            'text-left' => ! filled($href) && ! $stacked,
            'text-center' => $stacked,
        ])->merge(['data-ui' => $dataUi]) }}
    >
        @if ($hasCover)
            <x-ui.cover-backdrop
                :picture="$coverPicture"
                class="rounded-[inherit]"
            />
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
                    @class([
                        'flex size-16 shrink-0 items-center justify-center rounded-xl text-primary sm:size-[4.5rem]',
                        'bg-base-200/40' => $transparent,
                        'bg-base-200/90' => ! $transparent,
                    ])
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

    @if ($hasEdit)
        <div class="pointer-events-auto absolute right-2 top-2 z-30">
            <x-button
                :link="$editUrl"
                class="btn btn-xs btn-square rounded-lg bg-base-100/90 text-base-content/80 shadow-sm hover:text-primary"
                icon="o-pencil"
                :tooltip="__('ui.common.edit')"
                :aria-label="$editAriaLabel ?? __('ui.common.edit')"
                :data-ui="$editDataUi"
            />
        </div>
    @endif
</div>
