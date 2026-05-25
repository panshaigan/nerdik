@php
    use App\Models\ActivityType;
@endphp
<div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
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

        <div>
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

        <div class="ui-tile-empty rounded-2xl p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-range-dual
                    :label="__('ui.activities.participants')"
                    min-wire-model="min_participants"
                    max-wire-model="max_participants"
                    :min-limit="1"
                    :max-limit="20"
                    :step="1"
                    range-class="range-xs"
                />

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
                        class="range-xs"
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
                        class="range-xs"
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
                        class="range-xs"
                    />
                </div>
            </div>
        </div>

        <div class="ui-tile-empty rounded-2xl p-6">
            <x-toggle
                id="requires_approval"
                :label="__('ui.activities.requires_approval_badge')"
                wire:model="requires_approval"
                :hint="__('ui.activities.requires_approval')"
                class="mb-3"
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

<div class="pt-6">
    <x-editor
        wire:model="description"
        :gpl-license="true"
    />
    <x-field-error :messages="$errors->get('description')" class="mt-2" />
</div>
