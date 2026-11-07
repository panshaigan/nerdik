<div class="min-w-0" data-ui="group-availability-calendar">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-base-content">{{ __('ui.groups.calendar_title') }}</h2>
            <p class="mt-1 text-sm text-base-content/70">{{ __('ui.groups.calendar_intro', ['count' => $memberCount]) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-button type="button" class="btn-ghost btn-square btn-sm" icon="o-chevron-left" wire:click="previousMonth" :aria-label="__('ui.groups.previous_month')" />
            <span class="min-w-[10rem] text-center text-sm font-semibold">{{ $monthLabel }}</span>
            <x-button type="button" class="btn-ghost btn-square btn-sm" icon="o-chevron-right" wire:click="nextMonth" :aria-label="__('ui.groups.next_month')" />
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-lg border border-base-300 bg-base-100 p-4" data-ui="group-availability-filter">
        <x-input
            type="number"
            min="0"
            wire:model.live="minAvailable"
            :label="__('ui.groups.filter_min_available')"
            class="w-32"
        />
        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="requireAll" />
            <span class="label-text">{{ __('ui.groups.filter_require_all') }}</span>
        </label>
    </div>

    <div class="mt-4 overflow-x-auto">
        <div class="grid min-w-[20rem] grid-cols-7 gap-1 text-center text-xs font-medium text-base-content/70">
            @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $weekday)
                <div class="py-1">{{ __('ui.profile.availability.weekday_'.$weekday) }}</div>
            @endforeach
        </div>
        <div class="mt-1 grid min-w-[20rem] grid-cols-7 gap-1">
            @foreach ($weeks as $weekIndex => $week)
                @foreach ($week as $cell)
                    @php
                        $matches = $cell['counts']['matches_filter'] ?? false;
                        $inMonth = $cell['in_month'];
                        $available = (int) ($cell['counts']['available'] ?? 0);
                    @endphp
                    <button
                        type="button"
                        wire:key="cal-{{ $weekIndex }}-{{ $cell['date'] }}"
                        wire:click="{{ $inMonth ? "selectDate('".$cell['date']."')" : '' }}"
                        @disabled(! $inMonth)
                        class="@class([
                            'flex min-h-16 flex-col items-center justify-center rounded-lg border p-1 text-xs transition',
                            'border-base-300 bg-base-100 hover:border-primary' => $inMonth,
                            'border-transparent bg-base-200/30 opacity-40' => ! $inMonth,
                            'ring-2 ring-primary ring-offset-2 ring-offset-base-100' => $selectedDate === $cell['date'],
                            'border-success/60 bg-success/10' => $inMonth && $matches && $available > 0,
                        ])"
                        data-ui="group-calendar-day"
                    >
                        <span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($cell['date'])->format('j') }}</span>
                        @if ($inMonth)
                            <span class="mt-1 text-[0.65rem] text-base-content/80" title="{{ __('ui.availability.status_available') }}">
                                {{ $available }}/{{ $memberCount }}
                            </span>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </div>
    </div>

    @if ($selectedDate !== null && $dayDetail !== null)
        <x-modal wire:model="dayDetailOpen" :title="\Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('j F Y')" class="backdrop-blur">
            <div class="space-y-4" data-ui="group-day-detail">
                @foreach (['available' => 'success', 'unavailable' => 'error', 'uncertain' => 'warning'] as $bucket => $color)
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
