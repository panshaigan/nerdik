@props([
    'href',
    'title',
    'subtitle' => null,
    'imageUrl' => null,
    'icon' => null,
    'dataUi' => 'catalog-card',
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('ui-card ui-catalog-card ui-content-card card group flex h-full flex-col p-4 no-underline')->merge(['data-ui' => $dataUi]) }}
>
    <div class="flex min-w-0 flex-1 items-start gap-4">
        @if (filled($imageUrl))
            <img
                src="{{ $imageUrl }}"
                alt=""
                class="size-14 shrink-0 rounded-xl object-cover"
            />
        @elseif (filled($icon))
            <span
                class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-base-200 text-primary"
                aria-hidden="true"
            >
                <x-icon :name="$icon" class="h-7 w-7" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <h3 class="text-lg font-bold leading-snug text-neutral sm:text-xl">
                <span class="ui-link ui-link-title">{{ $title }}</span>
            </h3>
            @if (filled($subtitle))
                <p class="mt-1 text-sm text-base-content/70">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
</a>
