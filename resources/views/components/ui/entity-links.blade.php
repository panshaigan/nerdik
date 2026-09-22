@props([
    'links',
    'appearance' => 'default',
    'dataUi' => 'entity-links',
])

@php
    use Illuminate\Support\Collection;

    /** @var Collection<int, \App\Models\EntityLink>|iterable<\App\Models\EntityLink> $links */
    $collection = $links instanceof Collection ? $links : collect($links);
    $listClass = match ($appearance) {
        'compact' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm',
        'subtitle' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/80',
        'overlay' => 'flex flex-wrap items-center gap-x-3 gap-y-1 text-sm',
        default => 'flex flex-wrap items-center gap-x-4 gap-y-2 text-sm',
    };
    $linkClass = match ($appearance) {
        'overlay' => 'link link-hover text-white/90',
        default => 'link link-primary link-hover',
    };
@endphp

@if ($collection->isNotEmpty())
    <div {{ $attributes->class($listClass)->merge(['data-ui' => $dataUi]) }}>
        @foreach ($collection as $link)
            <a
                href="{{ $link->url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="{{ $linkClass }}"
                data-ui="{{ $dataUi }}-anchor"
            >{{ $link->name }}</a>
        @endforeach
    </div>
@endif
