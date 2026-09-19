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

<div
    class="relative z-[9999]"
    data-ui="calendar-menu"
    x-data="{
        open: false,
        toggle() {
            this.open = ! this.open;
        },
        close() {
            this.open = false;
        },
        openExternal(url) {
            window.open(url, '_blank', 'noopener,noreferrer');
            this.close();
        },
        downloadIcs(url) {
            window.location.href = url;
            this.close();
        },
    }"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
>
    <button
        type="button"
        class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
        x-on:click="toggle()"
        :aria-expanded="open"
        aria-haspopup="menu"
        :aria-label="@js(__('ui.calendar.add_to_calendar'))"
        title="{{ __('ui.calendar.add_to_calendar') }}"
        data-ui="calendar-menu-trigger"
    >
        <x-icon name="o-calendar" class="h-5 w-5" />
    </button>

    <ul
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="menu"
        class="absolute end-0 z-[9999] flex w-56 flex-col gap-0.5 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg light:border-neutral {{ $openUpward ? 'bottom-full mb-2' : 'top-full mt-2' }}"
        data-ui="calendar-menu-list"
        style="display: none;"
    >
        @foreach ($targets as $target)
            @php
                $intentUrl = $isSeries
                    ? $calendarLinks->seriesIntentUrl($payload, $target)
                    : $calendarLinks->intentUrl($payload, $target);
            @endphp
            @if ($intentUrl !== null)
                <li role="none">
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-base-200"
                        data-ui="calendar-{{ $target->value }}"
                        @if ($target->isExternal())
                            x-on:click="openExternal(@js($intentUrl))"
                        @else
                            x-on:click="downloadIcs(@js($intentUrl))"
                        @endif
                    >
                        <x-icon
                            :name="$platformIcon[$target->value]"
                            class="h-4 w-4 shrink-0"
                        />
                        {{ __('ui.calendar.targets.'.$target->value) }}
                    </button>
                </li>
            @endif
        @endforeach
    </ul>
</div>
