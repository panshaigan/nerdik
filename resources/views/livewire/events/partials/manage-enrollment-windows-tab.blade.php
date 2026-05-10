<div class="" data-ui="event-enrollment-windows-section">
    <p class="mb-6 text-sm text-base-content/80">{{ __('ui.events.enrollment_windows_help') }}</p>

    <div class="space-y-3">
        @foreach ($enrollment_windows as $index => $row)
            <div wire:key="enrollment-window-{{ $index }}" class="relative rounded-xl ui-tile-empty p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="min-w-0">
                        <x-input
                            wire:model.live="enrollment_windows.{{ $index }}.name"
                            type="text"
                            :label="__('ui.events.enrollment_window_name')"
                            :placeholder="__('ui.events.enrollment_window_name')"
                            class="w-full min-w-0"
                            inline
                        />
                        <x-field-error :messages="$errors->get('enrollment_windows.'.$index.'.name')" class="mt-2" />
                    </div>
                    <div class="min-w-0">
                        <x-input
                            wire:model.live="enrollment_windows.{{ $index }}.starts_at"
                            type="datetime-local"
                            :step="$datetimeMinuteStepSeconds"
                            :label="__('ui.events.enrollment_window_starts')"
                            :placeholder="__('ui.events.enrollment_window_starts')"
                            class="w-full min-w-0"
                            inline
                        />
                        <x-field-error :messages="$errors->get('enrollment_windows.'.$index.'.starts_at')" class="mt-2" />
                    </div>
                    <div class="min-w-0">
                        <x-input
                            wire:model.live="enrollment_windows.{{ $index }}.ends_at"
                            type="datetime-local"
                            :step="$datetimeMinuteStepSeconds"
                            :label="__('ui.events.enrollment_window_ends')"
                            :placeholder="__('ui.events.enrollment_window_ends')"
                            class="w-full min-w-0"
                            :max="$eventSignupPeriodMax ?? null"
                            inline
                        />
                        <x-field-error :messages="$errors->get('enrollment_windows.'.$index.'.ends_at')" class="mt-2" />
                    </div>
                    <div
                        x-data="{ value: @entangle('enrollment_windows.' . $index . '.max_activities_per_user') }"
                        x-init="$nextTick(() => value = value ?? 0)"
                        class="space-y-1"
                    >
                        <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.events.enrollment_window_max_activities') }}:
                            <span class="font-semibold" x-text="value == 0 ? '{{ __('ui.common.unlimited') }}' : value"></span>
                        </span>
                        </label>

                        <x-range
                            x-model="value"
                            min="0"
                            max="10"
                            step="1"
                            class="range-xs w-full"
                        />
                    </div>
                    <div
                        x-data="{
                            value: @entangle('enrollment_windows.' . $index . '.max_allowed_participants_per_activity'),
                            unlimitedLabel: @js(__('ui.common.unlimited'))
                        }"
                        x-init="$nextTick(() => value = value ?? 0)"
                        class="space-y-1"
                    >
                        <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.events.enrollment_window_max_participants_per_activity') }}:
                            <span class="font-semibold" x-text="value == 0 ? unlimitedLabel : value"></span>
                        </span>
                        </label>

                        <x-range
                            x-model="value"
                            min="0"
                            max="10"
                            step="1"
                            class="range-xs w-full"
                        />
                    </div>
                    <div class="flex items-center">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                wire:model="enrollment_windows.{{ $index }}.accumulative_activities"
                            />
                            <span class="text-xs text-base-content/80">{{ __('ui.events.enrollment_window_accumulative') }}</span>
                        </label>
                    </div>
                </div>

                @if ($index > 0)
                    <div class="flex justify-end">
                        <x-button
                            type="button"
                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                            wire:click="removeEnrollmentWindow({{ $index }})"
                            wire:loading.attr="disabled"
                            :title="__('Remove')"
                            :aria-label="__('Remove')"
                            data-ui="event-enrollment-window-remove"
                        >
                            <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                        </x-button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex justify-end">
        <x-button
            type="button"
            class="btn-neutral btn-sm"
            wire:click="addEnrollmentWindow"
            :disabled="!$canAddEnrollmentWindow"
            wire:loading.attr="disabled"
        >
            {{ __('ui.events.enrollment_window_add') }}
        </x-button>
    </div>

    <x-field-error :messages="$errors->get('enrollment_windows')" class="mt-2" />
</div>
