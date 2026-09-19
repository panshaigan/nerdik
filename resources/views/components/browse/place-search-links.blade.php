@props([
    'places' => [],
    'fallback' => '',
    'linkClass' => 'link link-primary break-words',
    'dataUi' => 'browse-place-search-link',
])
@php
    /** @var list<array{url: string, label: string}> $places */
    $places = is_array($places) ? $places : [];
@endphp
@if ($places !== [])
    <span class="min-w-0 leading-snug">
        @foreach ($places as $index => $placeLink)
            @if ($index > 0)
                <span aria-hidden="true">, </span>
            @endif
            <a
                href="{{ $placeLink['url'] }}"
                wire:navigate
                class="{{ $linkClass }}"
                data-ui="{{ $dataUi }}"
            >{{ $placeLink['label'] }}</a>
        @endforeach
    </span>
@elseif ($fallback !== '')
    <span class="min-w-0 leading-snug">{{ $fallback }}</span>
@endif
