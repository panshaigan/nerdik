<div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="relative">
            <x-input
                wire:model.live.debounce.300ms="name"
                label="{{ __('ui.activities.name') }}"
                placeholder="{{ __('ui.activities.name') }}"
                type="text"
                error-field="name"
                required
                autocomplete="off"
                data-activity-name-input
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="activity-name-suggestions-popup"
                icon="o-bookmark"
                inline
            />
            <div
                id="activity-name-suggestions-popup"
                class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                data-activity-name-popup
                role="listbox"
                wire:ignore
            ></div>
        </div>

        <div>
            <x-select
                id="activity_type_id"
                wire:model="activity_type_id"
                :label="__('ui.activities.type')"
                error-field="activity_type_id"
                required
                :options="$activityTypes->map(fn ($type) => ['id' => $type->id, 'name' => __('ui.activities.types.'.$type->slug)])->values()->all()"
                :placeholder="__('ui.activities.choose_type')"
                placeholder-value=""
                icon="o-squares-2x2"
                inline
            />
        </div>

        <div class="card border border-base-300 p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                    x-data="{
                        min: @entangle('min_participants'),
                        max: @entangle('max_participants'),
                        minLimit: 1,
                        maxLimit: 20,

                        get minPercent() {
                            const val = Number(this.min ?? this.minLimit);
                            const lo = this.minLimit;
                            const hi = this.maxLimit;
                            if (!Number.isFinite(val)) {
                                return 0;
                            }

                            return ((val - lo) / (hi - lo)) * 100;
                        },
                        get maxPercent() {
                            const val = Number(this.max ?? this.maxLimit);
                            const lo = this.minLimit;
                            const hi = this.maxLimit;
                            if (!Number.isFinite(val)) {
                                return 100;
                            }

                            return ((val - lo) / (hi - lo)) * 100;
                        },

                        init() {
                            this.min = this.min ?? this.minLimit;
                            this.max = this.max ?? this.maxLimit;

                            this.$watch('min', v => {
                                const val = Number(v);
                                const maxVal = Number(this.max);
                                if (val > maxVal) this.min = maxVal;
                            });

                            this.$watch('max', v => {
                                const val = Number(v);
                                const minVal = Number(this.min);
                                if (val < minVal) this.max = minVal;
                            });
                        }
                    }"
                    class="space-y-1"
                >
                    <!-- Label -->
                    <label class="text-sm font-medium flex justify-between">
                        <span>{{ __('ui.activities.participants') }}</span>
                        <span class="font-semibold" x-text="`${min}–${max}`"></span>
                    </label>

                    {{-- Dual range: Daisy .range / Mary <x-range> sizing; native fill off (.thumb-only); see .participants-dual-range in app.css --}}
                    <div class="participants-dual-range text-base-content">
                        <div class="participants-dual-range-track" aria-hidden="true"></div>
                        <div
                            class="participants-dual-range-fill"
                            aria-hidden="true"
                            :style="{ left: minPercent + '%', width: Math.max(0, maxPercent - minPercent) + '%' }"
                        ></div>

                        <input
                            type="range"
                            x-model.number="min"
                            :min="minLimit"
                            :max="maxLimit"
                            step="1"
                            class="thumb-only range absolute top-1/2 left-0 z-20 w-full -translate-y-1/2"
                            :class="min > (maxLimit / 2) ? 'z-30' : 'z-20'"
                        >
                        <input
                            type="range"
                            x-model.number="max"
                            :min="minLimit"
                            :max="maxLimit"
                            step="1"
                            class="thumb-only range absolute top-1/2 left-0 z-10 w-full -translate-y-1/2"
                            :class="max <= (maxLimit / 2) ? 'z-30' : 'z-10'"
                        >
                    </div>
                </div>

                <div
                    x-data="{ value: @entangle('minimum_age') }"
                    x-init="$nextTick(() => value = value ?? 0)"
                    class="space-y-1"
                >
                    <label class="text-sm font-medium flex justify-between">
                        <span>{{ __('ui.activities.minimum_age') }}: <span class="font-semibold" x-text="value"></span></span>
                    </label>
                    <x-range
                        x-model="value"
                        min="0"
                        max="18"
                    />
                </div>

                <div x-data="{ value: @entangle('duration_in_minutes') }" class="space-y-1">
                    <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.activities.duration_in_minutes') }}:
                            <span class="font-semibold">
                                <span x-text="Math.floor(value / 60)"></span>{{ __('ui.activities.duration_hours_short') }}
                                <span x-show="value % 60 > 0">
                                    <span x-text="value % 60"></span>{{ __('ui.activities.duration_minutes_short') }}
                                </span>
                            </span>
                        </span>
                    </label>
                    <x-range
                        x-model="value"
                        min="30"
                        max="720"
                        step="30"
                    />
                </div>

                <div x-data="{ value: @entangle('cancellation_deadline_in_hours') }" class="space-y-1">
                    <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.activities.cancellation_deadline_in_hours') }}:
                            <span class="font-semibold">
                                <span x-text="Math.floor(value / 24)" x-show="value >= 24"></span>
                                <span x-text="Math.floor(value / 24) === 1 ? '{{ __('ui.activities.duration_day') }}' : '{{ __('ui.activities.duration_days') }}'" x-show="value >= 24"></span>
                                <span x-show="value % 24 > 0">
                                    <span x-text="value % 24"></span>{{ __('ui.activities.duration_hours_short') }}
                                </span>
                            </span>
                        </span>
                        <x-popover class="transition-none">
                            <x-slot:trigger>
                                <x-icon name="o-information-circle" class="" :popover="__('ui.activities.cancellation_deadline_description')"/>
                            </x-slot:trigger>
                            <x-slot:content>
                                {{ __('ui.activities.cancellation_deadline_description') }}
                            </x-slot:content>
                        </x-popover>
                    </label>
                    <x-range
                        x-model="value"
                        min="0"
                        max="48"
                        step="6"
                    />
                </div>
            </div>
        </div>

        <div class="card border border-base-300 p-6 gap-y-3">
            <x-toggle
                id="requires_approval"
                :label="__('ui.activities.requires_approval_badge')"
                wire:model="requires_approval"
                :hint="__('ui.activities.requires_approval')"
                right
            />
            <x-toggle
                id="allows_observers"
                :label="__('ui.activities.allows_observers_badge')"
                wire:model="allows_observers"
                :hint="__('ui.activities.allows_observers')"
                right
            />
        </div>
    </div>
</div>

<div>
    <x-editor
        wire:model="description"
        :label="__('ui.activities.description')"
        :gpl-license="true"
        popover="dsadasd"
    />
    <x-field-error :messages="$errors->get('description')" class="mt-2" />
</div>
