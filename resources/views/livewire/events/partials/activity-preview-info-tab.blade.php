@php
    $slot = $activity->slot;
    $slotPlace = $slot?->place;
    $startsAt = $slot?->starts_at;
    $endsAt = $slot?->ends_at;
@endphp

<div class="space-y-5 pt-2" data-ui="event-activity-preview-info">
    <div class="rounded-xl border border-base-300 bg-base-100/70 p-4">
        <div class="space-y-2">
            <h3 class="text-lg font-semibold leading-snug text-base-content">{{ $activity->name }}</h3>

            @if ($activity->isCancelled())
                <div class="space-y-1">
                    <span class="badge badge-warning">{{ __('ui.activities.cancelled_badge') }}</span>
                    @if ($activity->cancel_reason)
                        <p class="text-xs text-error">{{ __('ui.activities.cancel_reason_label') }}: {{ $activity->cancel_reason }}</p>
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                @if ($slot?->name)
                    <span class="font-medium text-base-content/85">{{ $slot->name }}</span>
                @endif

                @if ($startsAt || $endsAt)
                    <span class="inline-flex items-center gap-1.5 tabular-nums text-base-content/75">
                        <x-icon name="o-clock" class="h-4 w-4 shrink-0 text-base-content/50" />
                        <span>
                            @if ($startsAt && $endsAt)
                                {{ format_in_user_tz($startsAt, 'H:i') }}<span class="text-base-content/45"> – </span>{{ format_in_user_tz($endsAt, 'H:i') }}
                            @elseif ($startsAt)
                                {{ format_in_user_tz($startsAt, 'H:i') }}
                            @else
                                {{ format_in_user_tz($endsAt, 'H:i') }}
                            @endif
                        </span>
                    </span>
                @endif

                <span class="badge badge-primary badge-sm">
                    {{ (int) $activity->participants->count() }}/{{ $activity->max_participants ?? '∞' }}
                </span>
            </div>

            @if ($slotPlace)
                <p class="inline-flex items-center gap-1.5 text-sm text-base-content/60">
                    <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                    <span>{{ $slotPlace->venueRoomLabel() }}</span>
                </p>
            @endif
        </div>
    </div>

    <x-ui.activity-badge-group
        :items="$badgeItems"
        data-ui="event-activity-preview-badge-group"
    />

    <div class="rounded-xl border border-base-300 bg-base-100/70 p-4">
        @if (filled(rich_text_excerpt($activity->description)))
            <div class="rich-text-content text-sm leading-relaxed text-base-content/90">
                {!! rich_text($activity->description) !!}
            </div>
        @else
            <p class="text-sm text-base-content/60">{{ __('ui.activities.show_no_description') }}</p>
        @endif
    </div>
</div>
