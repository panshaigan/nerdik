<ul class="mt-4 grid grid-cols-1 gap-3 text-sm text-base-content/90 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($event->enrollmentWindows as $window)
        @php
            $isThisWindowActive = $activeEnrollmentWindow && $activeEnrollmentWindow->is($window);
            $maxLabel = $window->maxActivitiesPerUserEffective();
        @endphp
        <li
            @class([
                'rounded-xl border px-6 py-6',
                'border-success/50 bg-success/5' => $isThisWindowActive,
                'border-primary/25 bg-base-200/40' => ! $isThisWindowActive,
            ])
            data-ui="event-show-enrollment-window"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 pb-3">
                <span class="font-semibold text-base-content">{{ $window->name }}</span>
                @if ($isThisWindowActive)
                    <span class="badge badge-success badge-sm shrink-0">{{ __('ui.events.enrollment_window_active_badge') }}</span>
                @endif
            </div>
            <div class="py-3">
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
            </div>
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
