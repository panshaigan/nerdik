@php
    $eventPlaces = $event->places
        ->filter(fn ($place) => $place
            && $place->type === 'venue'
            && $place->latitude !== null
            && $place->longitude !== null)
        ->unique('id')
        ->values();
    $eventPlacesMapConfig = [
        'places' => $eventPlaces->map(fn ($place) => [
            'name' => (string) $place->name,
            'lat' => (float) $place->latitude,
            'lng' => (float) $place->longitude,
        ])->all(),
    ];
    $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $eventPlaceSummary = $event->compactPlaceSummary();
@endphp
<div
    class="relative isolate overflow-hidden rounded-b-xl border border-primary/25"
    data-event-show-map-root
    wire:ignore
>
    <script type="application/json" data-event-show-map-config>@json($eventPlacesMapConfig)</script>
    <div
        id="ui-event-show-map"
        data-event-show-map
        class="relative z-0 w-full bg-base-200/40"
        style="min-height: 520px; height: min(840px, 100vh);"
        data-ui="event-show-map"
    ></div>
    <div class="absolute inset-x-0 bottom-0 z-50 bg-base-100/75 px-4 py-3 text-base-content backdrop-blur-[1px]">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium">{{ $eventPlaceSummary }}</p>
                <p class="text-xs text-white/90">{{ $eventDateSummary }}</p>
            </div>
            <div class="text-right">
                @if ($event->hostDisplayName())
                    <p class="text-xs text-white/90">{{ __('Organized by') }} {{ $event->hostDisplayName() }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
