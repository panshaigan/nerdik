@props([
    'payload',
    'openUpward' => false,
])

@php
    $calendarLinks = app(\App\Support\Calendar\CalendarLinks::class);
    $targets = \App\Support\Calendar\CalendarTarget::menuCases();
    $isSeries = $payload instanceof \App\Support\Calendar\CalendarSeriesPayload;
    $platformIcon = [
        \App\Support\Calendar\CalendarTarget::Google->value => 'o-calendar',
        \App\Support\Calendar\CalendarTarget::Outlook->value => 'o-calendar-days',
        \App\Support\Calendar\CalendarTarget::Download->value => 'o-arrow-down-tray',
    ];
@endphp

<div data-ui="calendar-menu">
    <x-ui.overflow-menu
        icon="o-calendar"
        :label="__('ui.calendar.add_to_calendar')"
        :open-upward="$openUpward"
        panel-class="w-56"
        data-ui="calendar-menu-trigger"
        list-data-ui="calendar-menu-list"
    >
        @foreach ($targets as $target)
            @php
                $intentUrl = $isSeries
                    ? $calendarLinks->seriesIntentUrl($payload, $target)
                    : $calendarLinks->intentUrl($payload, $target);
            @endphp
            @if ($intentUrl !== null)
                <x-ui.overflow-menu-item
                    :icon="$platformIcon[$target->value]"
                    :href="$intentUrl"
                    :external="$target->isExternal()"
                    data-ui="calendar-{{ $target->value }}"
                >
                    {{ __('ui.calendar.targets.'.$target->value) }}
                </x-ui.overflow-menu-item>
            @endif
        @endforeach
    </x-ui.overflow-menu>
</div>
