<div class="flex flex-col" data-ui="activity-show-info">
    <div class="mb-2 px-6 pb-6 pt-6 sm:px-8 sm:pt-8">
        @if (filled(rich_text_excerpt($activity->description)))
            <div class="rich-text-content text-base-content/90">
                {!! rich_text($activity->description) !!}
            </div>
        @else
            <p class="text-sm text-base-content/60">{{ __('ui.activities.show_no_description') }}</p>
        @endif
    </div>

    <div
        class="relative isolate w-full overflow-hidden rounded-b-xl"
        data-event-show-map-root
        wire:ignore
    >
        <script type="application/json" data-event-show-map-config>@json($scheduleMapConfig)</script>
        <div
            data-event-show-map
            class="relative z-0 w-full bg-base-200/30"
            style="min-height: 280px; height: min(420px, 52vh);"
            data-ui="activity-show-schedule-map"
        ></div>
        <div class="absolute inset-x-0 bottom-0 z-20 bg-black/60 px-4 py-3 text-white backdrop-blur-[1px]" data-ui="activity-show-schedule-overlay">
            <div class="space-y-1">
                @if ($scheduleVenue)
                    <p class="text-sm">
                        <span class="font-semibold text-xl">{{ $scheduleVenue->name }}</span>
                        @if ($scheduleRoom)
                            <span>({{ $scheduleRoom }})</span>
                        @endif
                        ·
                        <span>{{ $scheduleVenue?->address ?: __('ui.common.none') }}</span>,
                        @if ($scheduleVenue?->city)
                            <span>{{ $scheduleVenue->city->name(app()->getLocale()) }}</span>
                        @endif
                    </p>
                @endif
                <p class="text-xs text-white/90">
                    {{ $scheduleDateSummary ?: __('ui.activities.show_open_run') }}
                </p>
            </div>
        </div>
    </div>
</div>
