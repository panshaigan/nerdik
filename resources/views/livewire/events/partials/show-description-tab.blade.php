@php
    $hasEventDescription = filled(rich_text_excerpt($event->description));
    $hasEnrollmentWindows = $event->enrollmentWindows->isNotEmpty();
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
@endphp
<div class="space-y-6 p-4 sm:p-6">
    @if ($hasEventDescription)
        <div class="rich-text-content text-justify rounded-xl border border-primary/25 bg-base-200/40 p-6 text-base-content/80">
            {!! rich_text($event->description) !!}
        </div>
    @endif

    @if ($hasEnrollmentWindows)
        <div @class(['border-t border-primary/25 pt-6' => $hasEventDescription])>
            <h3 class="text-base font-semibold text-base-content">{{ __('ui.events.enrollment_windows_heading') }}</h3>
            @if ($activeEnrollmentWindow)
                <div
                    role="status"
                    class="mt-3 flex items-center gap-2 rounded-lg border border-success/40 bg-success/20 px-3 py-2 text-sm text-success-content"
                    data-ui="event-show-enrollment-open"
                >
                    <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-success" aria-hidden="true"></span>
                    <span>{{ __('ui.events.enrollment_open_now') }}</span>
                </div>
            @endif
            <ul class="mt-3 grid grid-cols-1 gap-3 text-sm text-base-content/90 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($event->enrollmentWindows as $window)
                    @php
                        $isThisWindowActive = $activeEnrollmentWindow && $activeEnrollmentWindow->is($window);
                        $maxLabel = $window->maxActivitiesPerUserEffective();
                    @endphp
                    <li
                        @class([
                            'rounded-xl border px-3 py-2',
                            'border-success/50 bg-success/5' => $isThisWindowActive,
                            'border-primary/25 bg-base-200/40' => ! $isThisWindowActive,
                        ])
                        data-ui="event-show-enrollment-window"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <span class="font-semibold text-base-content">{{ $window->name }}</span>
                            @if ($isThisWindowActive)
                                <span class="badge badge-success badge-sm shrink-0">{{ __('ui.events.enrollment_window_active_badge') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 tabular-nums text-base-content/80">
                            <span class="font-medium text-base-content/70">{{ __('ui.events.enrollment_window_starts') }}:</span>
                            <x-icon name="o-calendar" />
                            {{ format_datetime_in_user_tz($window->starts_at, 'D MMM YYYY, HH:mm') }}
                        </p>
                        <p class="mt-0.5 tabular-nums text-base-content/80">
                            <span class="font-medium text-base-content/70">{{ __('ui.events.enrollment_window_ends') }}:</span>
                            <x-icon name="o-calendar" />
                            {{ format_datetime_in_user_tz($window->ends_at, 'D MMM YYYY, HH:mm') }}
                        </p>
                        @if ($maxLabel !== null)
                            <p class="mt-1 text-xs text-base-content/70">
                                {{ __('ui.events.enrollment_window_max_activities') }}:
                                <span class="tabular-nums font-medium text-base-content/90">{{ $maxLabel }}</span>
                            </p>
                        @endif
                        @if ($window->maxAllowedParticipantsPerActivityEffective() !== null)
                            <p class="mt-1 text-xs text-base-content/70">
                                {{ __('ui.events.enrollment_window_max_participants_per_activity') }}:
                                <span class="tabular-nums font-medium text-base-content/90">{{ $window->maxAllowedParticipantsPerActivityEffective() }}</span>
                            </p>
                        @endif
                        @if ($window->accumulative_activities)
                            <p class="mt-1 text-xs text-base-content/70">
                                {{ __('ui.events.enrollment_window_accumulative_hint') }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
<div class="relative isolate" data-event-show-map-root wire:ignore>
    <script type="application/json" data-event-show-map-config>@json($eventPlacesMapConfig)</script>
    <div
        id="ui-event-show-map"
        data-event-show-map
        class="relative z-0 w-full bg-base-200/40"
        style="min-height: 260px; height: min(420px, 50vh);"
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
