<div class="min-w-0" data-ui="group-availability-calendar">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h2 class="text-lg font-medium text-base-content">{{ __('ui.groups.calendar_title') }}</h2>
        <div class="flex items-center gap-2">
            <x-button type="button" class="btn-ghost btn-square btn-sm" icon="o-chevron-left" wire:click="previousMonth" :aria-label="__('ui.groups.previous_month')" />
            <span class="min-w-[10rem] text-center text-sm font-semibold">{{ $monthLabel }}</span>
            <x-button type="button" class="btn-ghost btn-square btn-sm" icon="o-chevron-right" wire:click="nextMonth" :aria-label="__('ui.groups.next_month')" />
        </div>
    </div>

    <section
        class="mt-5 overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm"
        data-ui="group-availability-filter"
        aria-labelledby="group-availability-filter-heading"
    >
        <div class="border-b border-base-300 bg-base-200/50 px-4 py-3 sm:px-5">
            <h3 id="group-availability-filter-heading" class="text-sm font-semibold text-base-content">
                {{ __('ui.groups.filter_heading') }}
            </h3>
        </div>
        <div class="space-y-2 p-4 sm:px-5 sm:py-4">
            <label class="flex justify-between text-sm font-medium">
                <span>
                    {{ __('ui.groups.filter_min_available') }}:
                    <span class="font-semibold">
                        {{ $minAvailable > 0 ? $minAvailable : __('ui.browse.any') }}
                    </span>
                </span>
            </label>
            <x-range
                wire:model.live="minAvailable"
                min="0"
                max="{{ max(1, $memberCount) }}"
                step="1"
                class="range-xs w-full"
            />
        </div>
    </section>

    <div class="mt-8 overflow-x-auto">
        <div class="grid min-w-[20rem] grid-cols-7 gap-1 rounded-lg bg-base-200/70 px-1 py-2 text-center text-xs font-semibold uppercase tracking-wide text-base-content">
            @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $weekday)
                <div>{{ __('ui.profile.availability.weekday_'.$weekday) }}</div>
            @endforeach
        </div>
        <div class="mt-2 grid min-w-[20rem] grid-cols-7 gap-1">
            @foreach ($weeks as $weekIndex => $week)
                @foreach ($week as $cell)
                    @php
                        $matches = (bool) ($cell['counts']['matches_filter'] ?? false);
                        $inMonth = $cell['in_month'];
                        $available = (int) ($cell['counts']['available'] ?? 0);
                        $total = max(1, (int) ($cell['counts']['total'] ?? $memberCount ?: 1));
                        $ratio = $memberCount > 0 ? $available / $total : 0;
                        $hue = (int) round($ratio * 120);
                        $dayStyle = $inMonth
                            ? sprintf(
                                'background-color: hsl(%d 72%% 88%%); border-color: hsl(%d 55%% 42%%); color: hsl(%d 45%% 18%%);',
                                $hue,
                                $hue,
                                $hue,
                            )
                            : null;
                        $filteredOut = $filterActive && $inMonth && ! $matches;
                    @endphp
                    <button
                        type="button"
                        wire:key="cal-{{ $weekIndex }}-{{ $cell['date'] }}"
                        @if ($inMonth)
                            wire:click="selectDate('{{ $cell['date'] }}')"
                        @endif
                        @disabled(! $inMonth)
                        @style([$dayStyle => $dayStyle !== null])
                        @class([
                            'flex min-h-16 flex-col items-center justify-center rounded-lg border p-1 text-xs transition duration-150',
                            'cursor-pointer hover:-translate-y-0.5 hover:brightness-95 hover:shadow-md hover:shadow-base-content/10 active:translate-y-0 active:brightness-90' => $inMonth && ! $filteredOut,
                            'cursor-pointer hover:opacity-50' => $filteredOut,
                            'cursor-default border-transparent bg-base-200/30 opacity-40' => ! $inMonth,
                            'opacity-25 grayscale' => $filteredOut,
                            'ring-2 ring-primary ring-offset-2 ring-offset-base-100' => $selectedDate === $cell['date'],
                            'ring-2 ring-base-content/40 ring-offset-1 ring-offset-base-100' => $filterActive && $inMonth && $matches && $selectedDate !== $cell['date'],
                        ])
                        data-ui="group-calendar-day"
                        @if ($filterActive && $inMonth)
                            data-matches-filter="{{ $matches ? '1' : '0' }}"
                        @endif
                    >
                        <span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($cell['date'])->format('j') }}</span>
                        @if ($inMonth)
                            <span class="mt-1 text-[0.65rem] font-medium opacity-90" title="{{ __('ui.availability.status_available') }}">
                                {{ $available }}/{{ $memberCount }}
                            </span>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </div>
    </div>

    @if ($selectedDate !== null && $dayDetail !== null)
        <x-modal wire:model="dayDetailOpen" :title="format_date_in_user_tz($selectedDate)" class="backdrop-blur">
            <div class="space-y-4" data-ui="group-day-detail">
                @foreach (['available', 'unavailable', 'uncertain'] as $bucket)
                    <div>
                        <h3 class="text-sm font-semibold text-base-content">
                            @switch($bucket)
                                @case('available') {{ __('ui.availability.status_available') }} ({{ $dayDetail['counts']['available'] }}) @break
                                @case('unavailable') {{ __('ui.availability.status_unavailable') }} ({{ $dayDetail['counts']['unavailable'] }}) @break
                                @case('uncertain') {{ __('ui.availability.status_uncertain') }} ({{ $dayDetail['counts']['uncertain'] }}) @break
                            @endswitch
                        </h3>
                        <ul class="mt-2 space-y-1">
                            @forelse ($dayDetail[$bucket] as $user)
                                <li wire:key="day-{{ $selectedDate }}-{{ $bucket }}-{{ $user->id }}">
                                    <x-user-badge :user="$user" />
                                </li>
                            @empty
                                <li class="text-sm text-base-content/60">—</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <x-button type="button" class="btn-ghost" wire:click="closeDayDetail">
                        {{ __('ui.common.close') }}
                    </x-button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
