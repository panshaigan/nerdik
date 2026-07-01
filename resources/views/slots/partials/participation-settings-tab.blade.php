@php
    use App\Enums\ParticipationMode;

    $fieldIdPrefix = $fieldIdPrefix ?? 'slot_participation';
    $defaultForcesParticipation = ($editMode && $slot)
        ? (bool) $slot->forces_participation_settings
        : false;
    $forcesParticipationChecked = filter_var(
        old('forces_participation_settings', $defaultForcesParticipation ? '1' : '0'),
        FILTER_VALIDATE_BOOLEAN
    );
    $defaultParticipationMode = ($editMode && $slot && $slot->participation_mode)
        ? $slot->participation_mode->value
        : ParticipationMode::Open->value;
    $participationModeValue = old('participation_mode', $defaultParticipationMode);
    $defaultLotteryDrawInHours = ($editMode && $slot && $slot->lottery_draw_in_hours !== null)
        ? (int) $slot->lottery_draw_in_hours
        : 24;
    $lotteryDrawInHoursValue = (int) old('lottery_draw_in_hours', $defaultLotteryDrawInHours);
    $defaultAllowsObservers = ($editMode && $slot)
        ? (bool) $slot->allows_observers
        : false;
    $allowsObserversChecked = filter_var(
        old('allows_observers', $defaultAllowsObservers ? '1' : '0'),
        FILTER_VALIDATE_BOOLEAN
    );
    $participationOptions = [
        ['id' => ParticipationMode::Open->value, 'name' => __('ui.activities.participation_mode_open'), 'hint' => __('ui.activities.participation_mode_open_hint')],
        ['id' => ParticipationMode::HostApproval->value, 'name' => __('ui.activities.participation_mode_host_approval'), 'hint' => __('ui.activities.participation_mode_host_approval_hint')],
        ['id' => ParticipationMode::Lottery->value, 'name' => __('ui.activities.participation_mode_lottery'), 'hint' => __('ui.activities.participation_mode_lottery_hint')],
    ];
@endphp

<div
    x-data="{
        forcesParticipation: @js($forcesParticipationChecked),
        participationMode: @js($participationModeValue),
        lotteryDrawInHours: {{ $lotteryDrawInHoursValue }},
        localLotteryValue: {{ $lotteryDrawInHoursValue }},
        allowsObservers: @js($allowsObserversChecked),
        dayLabel: @js(__('ui.activities.duration_day')),
        daysLabel: @js(__('ui.activities.duration_days')),
        hoursShort: @js(__('ui.activities.duration_hours_short')),
        formatDeadline() {
            const v = Number(this.localLotteryValue);
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
    }"
    class="space-y-4"
    data-ui="slot-participation-settings"
>
    <input type="hidden" name="forces_participation_settings" value="0">
    <div class="flex items-start gap-2 rounded-xl border border-base-300 bg-base-200/30 p-4">
        <input
            id="{{ $fieldIdPrefix }}_forces"
            name="forces_participation_settings"
            type="checkbox"
            value="1"
            class="checkbox checkbox-sm mt-0.5"
            x-model="forcesParticipation"
            @checked($forcesParticipationChecked)
        />
        <div class="min-w-0">
            <label for="{{ $fieldIdPrefix }}_forces" class="label cursor-pointer text-sm font-medium text-base-content">
                {{ __('ui.slots.forces_participation_settings') }}
            </label>
            <p class="text-sm text-base-content/60">{{ __('ui.slots.forces_participation_settings_help') }}</p>
        </div>
    </div>

    <fieldset class="ui-tile-empty min-w-0 space-y-6 rounded-2xl p-4 sm:p-6" :disabled="!forcesParticipation">
        <legend class="sr-only">{{ __('ui.slots.tab_participation') }}</legend>

        <div class="space-y-3">
            <p class="text-sm font-medium text-base-content">{{ __('ui.activities.participation_mode') }}</p>
            @foreach ($participationOptions as $option)
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 p-3 transition-colors hover:bg-base-200/40" :class="{ 'opacity-50 pointer-events-none': !forcesParticipation }">
                    <input
                        type="radio"
                        name="participation_mode"
                        value="{{ $option['id'] }}"
                        class="radio radio-sm mt-0.5"
                        x-model="participationMode"
                        @checked($participationModeValue === $option['id'])
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-base-content">{{ $option['name'] }}</span>
                        <span class="block text-sm text-base-content/60">{{ $option['hint'] }}</span>
                    </span>
                </label>
            @endforeach
            <x-field-error :messages="$errors->get('participation_mode')" class="mt-1" />
        </div>

        <div x-show="participationMode === 'lottery'" x-cloak class="min-w-0 space-y-1">
            <label class="flex min-w-0 justify-between gap-2 text-sm font-medium">
                <span class="min-w-0">
                    {{ __('ui.activities.lottery_draw_in_hours') }}:
                    <span class="font-semibold" x-text="formatDeadline()"></span>
                </span>
                <x-popover class="shrink-0 transition-none">
                    <x-slot:trigger>
                        <x-icon name="o-information-circle" :popover="__('ui.activities.lottery_draw_in_hours_description')"/>
                    </x-slot:trigger>
                    <x-slot:content>
                        {{ __('ui.activities.lottery_draw_in_hours_description') }}
                    </x-slot:content>
                </x-popover>
            </label>
            <input type="hidden" name="lottery_draw_in_hours" :value="localLotteryValue" :disabled="!forcesParticipation">
            <x-range
                x-model.number="localLotteryValue"
                @input="lotteryDrawInHours = Number(localLotteryValue)"
                min="1"
                max="48"
                step="1"
                class="range-xs w-full"
                ::disabled="!forcesParticipation"
            />
            <x-field-error :messages="$errors->get('lottery_draw_in_hours')" class="mt-1" />
        </div>

        <hr />

        <input type="hidden" name="allows_observers" value="0">
        <div class="flex items-center gap-2">
            <input
                id="{{ $fieldIdPrefix }}_allows_observers"
                name="allows_observers"
                type="checkbox"
                value="1"
                class="toggle toggle-sm"
                x-model="allowsObservers"
                @checked($allowsObserversChecked)
            />
            <label for="{{ $fieldIdPrefix }}_allows_observers" class="label cursor-pointer text-sm text-base-content">
                {{ __('ui.activities.allows_observers_badge') }}
            </label>
        </div>
        <p class="text-sm text-base-content/60">{{ __('ui.activities.allows_observers') }}</p>
        <x-field-error :messages="$errors->get('allows_observers')" class="mt-1" />
    </fieldset>
</div>
