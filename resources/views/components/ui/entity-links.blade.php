@props([
    'links',
    'appearance' => 'default',
    'dataUi' => 'entity-links',
    'showHeading' => true,
])

@php
    use Illuminate\Support\Collection;

    /** @var Collection<int, \App\Models\EntityLink>|iterable<\App\Models\EntityLink> $links */
    $collection = $links instanceof Collection ? $links : collect($links);
    $isOverlay = $appearance === 'overlay';
    $isCompact = in_array($appearance, ['compact', 'subtitle'], true);
    $useTile = ! $isOverlay && ! $isCompact;
    $linkClass = $isOverlay
        ? 'link link-hover text-white/90'
        : 'link link-secondary link-hover';
    $iconClass = $isOverlay
        ? 'h-4 w-4 shrink-0 text-white/70'
        : 'h-4 w-4 shrink-0 text-secondary';
@endphp

@if ($collection->isNotEmpty())
    <div
        @if ($useTile)
            {{ $attributes->class('rounded-xl border border-secondary/25 bg-secondary/5 px-4 py-3 mb-6')->merge(['data-ui' => $dataUi]) }}
        @else
            {{ $attributes->merge(['data-ui' => $dataUi]) }}
        @endif
    >
        @if ($showHeading && $useTile)
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/60" data-ui="{{ $dataUi }}-heading">
                {{ __('ui.entity_links.section') }}
            </p>
        @endif

        <ul
            @class([
                'flex flex-col gap-2 text-sm' => $useTile || $appearance === 'default',
                'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm' => $isCompact,
                'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm' => $isOverlay,
            ])
            data-ui="{{ $dataUi }}-list"
        >
            @foreach ($collection as $link)
                <li class="inline-flex min-w-0 items-center gap-1.5" data-ui="{{ $dataUi }}-item">
                    <x-icon name="o-link" class="{{ $iconClass }}" />
                    <a
                        href="{{ $link->url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="{{ $linkClass }} min-w-0 truncate"
                        data-ui="{{ $dataUi }}-anchor"
                    >{{ $link->name }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
