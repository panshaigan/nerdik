@php
    $about = $previewAbout;
@endphp

<div class="space-y-4" data-ui="event-activity-preview-info">
    <div class="space-y-2">
        @if ($activity->isCancelled())
            <div class="space-y-1">
                <span class="badge badge-warning">{{ __('ui.activities.cancelled_badge') }}</span>
                @if ($activity->cancel_reason)
                    <p class="text-xs text-error">{{ __('ui.activities.cancel_reason_label') }}: {{ $activity->cancel_reason }}</p>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
            @if ($about->slotName)
                <span class="font-medium text-base-content/85">{{ $about->slotName }}</span>
            @endif

            @if ($about->timeLabel !== '')
                <span class="inline-flex items-center gap-1.5 tabular-nums text-base-content/75">
                    <x-icon name="o-clock" class="h-4 w-4 shrink-0 text-base-content/50" />
                    <span>{{ $about->timeLabel }}</span>
                </span>
            @endif
        </div>

        @if ($about->locationLabel !== '')
            <p class="inline-flex items-center gap-1.5 text-sm text-base-content/60">
                <x-icon name="o-map-pin" class="h-4 w-4 shrink-0" />
                <span>{{ $about->locationLabel }}</span>
            </p>
        @endif
    </div>

    @if (filled(rich_text_excerpt($activity->description)))
        <div class="rich-text-content text-base-content/90">
            {!! rich_text($activity->description) !!}
        </div>
    @else
        <p class="text-sm text-base-content/60">{{ __('ui.activities.show_no_description') }}</p>
    @endif

    <x-ui.activity-badge-group
        :items="$badgeItems"
        data-ui="event-activity-preview-badge-group"
    />
</div>
