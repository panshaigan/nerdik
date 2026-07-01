@php
    use App\Models\ActivityType;
@endphp
<div>
    <div class="grid min-w-0 gap-4 sm:grid-cols-2">
        <div class="min-w-0">
            <x-input
                wire:model.live.debounce.300ms="name"
                label="{{ __('ui.activities.name') }}"
                placeholder="{{ __('ui.activities.name') }}"
                type="text"
                error-field="name"
                required
                autocomplete="off"
                data-activity-name-input
                icon="o-bookmark"
                inline
            />
        </div>

        <div class="min-w-0">
            <x-select
                id="activity_type_id"
                wire:model="activity_type_id"
                :label="__('ui.activities.type')"
                error-field="activity_type_id"
                required
                :options="$activityTypes->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => __('ui.activities.types.'.$type->slug),
                    'disabled' => $type->slug !== ActivityType::SLUG_RPG,
                ])->values()->all()"
                :placeholder="__('ui.activities.choose_type')"
                placeholder-value=""
                icon="o-squares-2x2"
                inline
            />
        </div>

        <div class="ui-tile-empty min-w-0 rounded-2xl p-4 sm:p-8">
            <div class="grid min-w-0 grid-cols-1 gap-4 space-y-4 md:grid-cols-1">
                <x-range-dual
                    class="min-w-0"
                    :label="__('ui.activities.participants')"
                    min-wire-model="min_participants"
                    max-wire-model="max_participants"
                    :min-limit="1"
                    :max-limit="20"
                    :step="1"
                    range-class="range-xs"
                />

                <div
                    x-data="{
                        value: @entangle('minimum_age'),
                        noLimitLabel: @js(__('ui.activities.minimum_age_no_limit')),
                    }"
                    x-init="$nextTick(() => value = value ?? 0)"
                    class="min-w-0 space-y-1"
                >
                    <label class="text-sm font-medium flex justify-between">
                        <span>{{ __('ui.activities.minimum_age') }}: <span class="font-semibold" x-text="value == 0 ? noLimitLabel : value"></span></span>
                    </label>
                    <x-range
                        x-model="value"
                        min="0"
                        max="18"
                        class="range-xs w-full"
                    />
                </div>

                <div
                    x-data="{
                        value: @entangle('duration_in_minutes'),
                        localValue: 30,
                        min: 30,
                        max: 720,
                        step: 30,
                        hoursShort: @js(__('ui.activities.duration_hours_short')),
                        minutesShort: @js(__('ui.activities.duration_minutes_short')),
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
                        formatDuration() {
                            const v = Number(this.localValue);
                            const h = Math.floor(v / 60);
                            const m = v % 60;
                            let parts = [];
                            if (h > 0) {
                                parts.push(`${h}${this.hoursShort}`);
                            }
                            if (m > 0) {
                                parts.push(`${m}${this.minutesShort}`);
                            }
                            return parts.join(' ') || `0${this.hoursShort}`;
                        },
                        onSliderInput() {
                            this.value = Number(this.localValue);
                        },
                        init() {
                            this.$nextTick(() => {
                                this.localValue = this.coerceSliderValue(this.value);
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
                    <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.activities.duration_in_minutes') }}:
                            <span class="font-semibold" x-text="formatDuration()"></span>
                        </span>
                    </label>
                    <x-range
                        x-model.number="localValue"
                        @input="onSliderInput()"
                        min="30"
                        max="720"
                        step="30"
                        class="range-xs w-full"
                    />
                </div>

                <div
                    x-data="{
                        value: @entangle('cancellation_deadline_in_hours'),
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
                            {{ __('ui.activities.cancellation_deadline_in_hours') }}:
                            <span class="font-semibold" x-text="formatDeadline()"></span>
                        </span>
                        <x-popover class="shrink-0 transition-none">
                            <x-slot:trigger>
                                <x-icon name="o-information-circle" class="" :popover="__('ui.activities.cancellation_deadline_description')"/>
                            </x-slot:trigger>
                            <x-slot:content>
                                {{ __('ui.activities.cancellation_deadline_description') }}
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
                    />
                </div>
            </div>
        </div>

        <div class="ui-tile-empty min-w-0 rounded-2xl space-y-6 p-4 sm:p-6">
            <x-radio
                wire:model.live="participation_mode"
                :label="__('ui.activities.participation_mode')"
                :options="[
                    ['id' => 'open', 'name' => __('ui.activities.participation_mode_open'), 'hint' => __('ui.activities.participation_mode_open_hint')],
                    ['id' => 'host_approval', 'name' => __('ui.activities.participation_mode_host_approval'), 'hint' => __('ui.activities.participation_mode_host_approval_hint')],
                    ['id' => 'lottery', 'name' => __('ui.activities.participation_mode_lottery'), 'hint' => __('ui.activities.participation_mode_lottery_hint')],
                ]"
                error-field="participation_mode"
                class="mb-3"
            />

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
            />
        </div>
    </div>
</div>

<div class="pt-6">
    <x-editor
        wire:model="description"
        :gpl-license="true"
    />
    <x-field-error :messages="$errors->get('description')" class="mt-2" />
</div>
