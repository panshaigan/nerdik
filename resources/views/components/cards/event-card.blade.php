@props([
    'event',
    'interestedEventIds' => [],
    'showListingKind' => false,
])

@php
    $locale = app()->getLocale();
    $placeNames = $event->places->pluck('name')->filter()->unique()->values();
    $placeChips = $placeNames->take(3);
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

<article class="ui-card ui-card-event card overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm" data-ui="event-card" id="ui-event-card-{{ $event->id }}">
    <div class="bg-gradient-to-br from-violet-900 via-purple-900 to-indigo-900 p-5 text-base-100" data-ui="event-card-body">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <span class="badge badge-sm border-0 bg-base-100/20 text-base-100">
                    {{ $showListingKind ? __('ui.browse.listing_kind_event') : __('Event') }}
                </span>
            </div>
            @auth
                <div class="shrink-0">
                    @if (in_array($event->id, $interestedEventIds))
                        <form action="{{ route('interests.events.remove', $event) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" class="btn btn-xs rounded-full border-base-100/50 bg-base-100/10 text-warning hover:bg-base-100/20 ui-action ui-action-interest-remove" :title="__('ui.interests.remove_from_interests')" data-ui="event-card-interest-remove">★</x-button>
                        </form>
                    @else
                        <form action="{{ route('interests.events.add', $event) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" class="btn btn-xs rounded-full border-base-100/50 bg-transparent text-base-100 hover:bg-base-100/20 ui-action ui-action-interest-add" :title="__('ui.interests.add_to_interests')" data-ui="event-card-interest-add">☆</x-button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        <h3 class="mb-1 text-3xl font-bold leading-tight">
            <a href="{{ route('events.show', $event) }}" wire:navigate class="ui-link ui-link-title hover:underline" data-ui="event-card-title-link">{{ $event->name }}</a>
        </h3>

        @if ($event->hostDisplayName())
            <p class="text-base text-base-100/85">{{ __('Host') }}: {{ $event->hostDisplayName() }}</p>
        @endif
    </div>

    <div class="space-y-4 p-5">
        <div class="grid grid-cols-2 gap-4 text-sm text-base-content/80">
            @if ($dateSummary !== '')
                <div class="space-y-0.5">
                    <p class="font-semibold text-base-content/75">{{ __('Date') }}:</p>
                    <p class="flex items-center gap-1.5 tabular-nums text-base-content">
                        <x-icon name="o-clock" class="h-4 w-4 text-base-content/60" />
                        <span>{{ $dateSummary }}</span>
                    </p>
                </div>
            @endif

            @if ($locationSummary !== '')
                <div class="space-y-0.5">
                    <p class="font-semibold text-base-content/75">{{ __('Location') }}:</p>
                    <p class="flex items-center gap-1.5 text-base-content">
                        <x-icon name="o-home" class="h-4 w-4 text-base-content/60" />
                        <span>{{ $locationSummary }}</span>
                    </p>
                </div>
            @endif
        </div>

        @if (filled(rich_text_excerpt($event->description)))
            <p class="line-clamp-2 text-sm text-base-content/80">{{ rich_text_excerpt($event->description, 160) }}</p>
        @endif

        @if ($placeChips->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($placeChips as $placeName)
                    <span class="badge rounded-full border-0 bg-info/20 px-3 py-2 text-xs font-medium text-info-content">
                        {{ $placeName }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</article>
