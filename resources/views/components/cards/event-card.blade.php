@props([
    'event',
    'wishlistEventIds' => [],
])

@php
    $locale = app()->getLocale();
    $placeNames = $event->places->pluck('name')->filter()->unique()->values();
    $locationLabels = [];
    foreach ($event->places as $place) {
        $city = $place->city?->name($locale);
        $country = $place->country?->name($locale);
        $c = $city ? trim($city) : null;
        $co = $country ? trim($country) : null;
        if ($c !== null && $co !== null && mb_strtolower($c) === mb_strtolower($co)) {
            $label = $c;
        } else {
            $label = implode(', ', array_filter([$c, $co]));
        }
        if ($label !== '') {
            $locationLabels[mb_strtolower($label)] = $label;
        }
    }
    $locationSummary = implode(' · ', array_values($locationLabels));
    $dateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
@endphp

<article class="ui-card ui-card-event card border border-base-300 bg-base-100 shadow-sm" data-ui="event-card" id="ui-event-card-{{ $event->id }}">
    <div class="card-body p-5" data-ui="event-card-body">
        <div class="flex items-start justify-between gap-2">
            <h3 class="card-title text-xl leading-tight">
                <a href="{{ route('events.show', $event) }}" wire:navigate class="link link-primary ui-link ui-link-title" data-ui="event-card-title-link">{{ $event->name }}</a>
            </h3>
            <div class="flex shrink-0 items-center gap-0.5">
                @auth
                    @if (in_array($event->id, $wishlistEventIds))
                        <form action="{{ route('wishlist.events.remove', $event) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" class="btn-ghost btn-sm text-warning ui-action ui-action-wishlist-remove" :title="__('Remove from wishlist')" data-ui="event-card-wishlist-remove">★</x-button>
                        </form>
                    @else
                        <form action="{{ route('wishlist.events.add', $event) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" class="btn-ghost btn-sm ui-action ui-action-wishlist-add" :title="__('Add to wishlist')" data-ui="event-card-wishlist-add">☆</x-button>
                        </form>
                    @endif
                @endauth
                <x-button
                    type="button"
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText('{{ route('events.show', $event) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="btn-ghost btn-sm ui-action ui-action-share"
                    :title="__('Copy link')"
                    data-ui="event-card-share"
                >
                    <span x-show="!copied">{{ __('Share') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </x-button>
            </div>
        </div>

        @if ($event->hostDisplayName())
            <p class="text-sm opacity-70">{{ __('Host') }}: {{ $event->hostDisplayName() }}</p>
        @endif

        @if ($dateSummary !== '')
            <p class="text-sm tabular-nums opacity-80">{{ $dateSummary }}</p>
        @endif

        @if ($placeNames->isNotEmpty())
            <p class="text-sm text-base-content/90">{{ $placeNames->implode(', ') }}</p>
        @endif

        @if ($locationSummary !== '')
            <p class="text-sm opacity-70">{{ $locationSummary }}</p>
        @endif

        @if (filled(rich_text_excerpt($event->desc)))
            <p class="line-clamp-3 text-sm opacity-80">{{ rich_text_excerpt($event->desc, 160) }}</p>
        @endif

        <div class="mt-2">
            @include('tags.partials.inline', ['tags' => $event->tags])
        </div>
    </div>
</article>
