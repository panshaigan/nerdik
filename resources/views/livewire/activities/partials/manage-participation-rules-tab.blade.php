<div class="ui-tile-empty min-w-0 rounded-2xl space-y-6 p-4 sm:p-6">
    @php
        $allParticipationOptions = [
            ['id' => 'open', 'name' => __('ui.activities.participation_mode_open'), 'hint' => __('ui.activities.participation_mode_open_hint')],
            ['id' => 'host_approval', 'name' => __('ui.activities.participation_mode_host_approval'), 'hint' => __('ui.activities.participation_mode_host_approval_hint')],
            ['id' => 'lottery', 'name' => __('ui.activities.participation_mode_lottery'), 'hint' => __('ui.activities.participation_mode_lottery_hint')],
        ];
        $participationOptions = ($participationConstrainedBySlots ?? false)
            ? array_values(array_filter(
                $allParticipationOptions,
                fn (array $option): bool => in_array($option['id'], $allowedParticipationModes ?? [], true),
            ))
            : $allParticipationOptions;
    @endphp
    <x-radio
        wire:model.live="participation_mode"
        :label="__('ui.activities.participation_mode')"
        :options="$participationOptions"
        error-field="participation_mode"
        class="mb-3"
        :disabled="$participationConstrainedBySlots && count($participationOptions) === 1"
    />

    @if ($participationConstrainedBySlots ?? false)
        <p class="text-sm text-base-content/60">{{ __('ui.slots.participation_constrained_by_preferred_slots') }}</p>
    @endif

    @if ($participation_mode === 'lottery')
        <div
            x-data="{
                value: @entangle('lottery_draw_in_hours'),
                localValue: 1,
                min: 1,
                max: 48,
                step: 1,
                dayLabel: @js(__('ui.activities.duration_day')),
                daysLabel: @js(__('ui.activities.duration_days')),
                hoursShort: @js(__('ui.activities.duration_hours_short')),
                snapToStep(raw, min, max, step) {
                    const snapped = Math.round((raw - min) / step) * step + min;
                    return Math.min(max, Math.max(min, snapped));
                },
                coerceSliderValue(wire) {
                    if (wire !== null && wire !== '') {
                        return Number(wire);
                    }
                    return this.snapToStep((this.min + this.max) / 2, this.min, this.max, this.step);
                },
                formatDeadline() {
                    const v = Number(this.localValue);
                    let parts = [];
                    if (v >= 24) {
                        const days = Math.floor(v / 24);
                        parts.push(`${days} ${days === 1 ? this.dayLabel : this.daysLabel}`);
                    }
                    if (v % 24 > 0) {
                        parts.push(`${v % 24}${this.hoursShort}`);
                    }
                    return parts.join(' ');
                },
                onSliderInput() {
                    this.value = Number(this.localValue);
                },
                init() {
                    this.$nextTick(() => {
                        this.localValue = this.coerceSliderValue(this.value);
                        if (this.value === null || this.value === '') {
                            this.value = this.localValue;
                        }
                        this.$watch('value', (v) => {
                            if (v !== null && v !== '') {
                                this.localValue = Number(v);
                            }
                        });
                    });
                },
            }"
            class="min-w-0 space-y-1"
        >
            <label class="flex min-w-0 justify-between gap-2 text-sm font-medium">
                <span class="min-w-0">
                    {{ __('ui.activities.lottery_draw_in_hours') }}:
                    <span class="font-semibold" x-text="formatDeadline()"></span>
                </span>
                <x-popover class="shrink-0 transition-none">
                    <x-slot:trigger>
                        <x-icon name="o-information-circle" class="" :popover="__('ui.activities.lottery_draw_in_hours_description')"/>
                    </x-slot:trigger>
                    <x-slot:content>
                        {{ __('ui.activities.lottery_draw_in_hours_description') }}
                    </x-slot:content>
                </x-popover>
            </label>
            <x-range
                x-model.number="localValue"
                @input="onSliderInput()"
                min="1"
                max="48"
                step="1"
                class="range-xs w-full"
                @disabled($lotteryDrawHoursLockedBySlots ?? false)
            />
            <x-field-error :messages="$errors->get('lottery_draw_in_hours')" class="mt-1" />
        </div>
    @endif

    <hr />
    <x-toggle
        id="allows_observers"
        :label="__('ui.activities.allows_observers_badge')"
        wire:model="allows_observers"
        :hint="__('ui.activities.allows_observers')"
        :disabled="$allowsObserversLockedBySlots ?? false"
    />

    <x-toggle
        id="collect_familiarity"
        :label="__('ui.activities.collect_familiarity_badge')"
        wire:model="collect_familiarity"
        :hint="__('ui.activities.collect_familiarity')"
    />
</div>
