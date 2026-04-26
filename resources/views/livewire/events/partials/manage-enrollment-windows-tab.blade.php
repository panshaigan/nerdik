<div class="" data-ui="event-enrollment-windows-section">
    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.enrollment_windows_help') }}</p>

    <div class="space-y-3">
        @foreach ($enrollment_windows as $index => $row)
            <div wire:key="enrollment-window-{{ $index }}" class="rounded-lg border border-base-300 bg-base-100/80 p-3 sm:p-4">
                <div class="flex min-w-0 flex-nowrap items-end gap-2 overflow-x-auto pb-0.5 sm:gap-3">
                    <div class="min-w-[11rem] shrink-0 sm:min-w-0 sm:flex-1">
                        <x-input
                            wire:model.live="enrollment_windows.{{ $index }}.starts_at"
                            type="datetime-local"
                            :step="$datetimeMinuteStepSeconds"
                            :label="__('ui.events.enrollment_window_starts')"
                            :placeholder="__('ui.events.enrollment_window_starts')"
                            class="w-full min-w-0"
                            inline
                        />
                        <x-field-error :messages="$errors->get('enrollment_windows.'.$index)" class="mt-2" />
                    </div>
                    <div class="min-w-[11rem] shrink-0 sm:min-w-0 sm:flex-1">
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
                    </div>
                    <div
                        x-data="{ value: @entangle('enrollment_windows.' . $index . '.max_activities_per_user') }"
                        x-init="$nextTick(() => value = value ?? 0)"
                        class="space-y-1 min-w-[6.5rem] max-w-[9rem] shrink-0"
                    >
                        <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.events.enrollment_window_max_activities') }}:
                            <span class="font-semibold" x-text="value"></span>
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
                        x-data="{ value: @entangle('enrollment_windows.' . $index . '.max_allowed_participants_per_activity') }"
                        x-init="$nextTick(() => value = value ?? 0)"
                        class="space-y-1 min-w-[6.5rem] max-w-[9rem] shrink-0"
                    >
                        <label class="text-sm font-medium flex justify-between">
                        <span>
                            {{ __('ui.events.enrollment_window_max_activities') }}:
                            <span class="font-semibold" x-text="value"></span>
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
                    <div class="min-w-[8rem] shrink-0 self-center pt-5">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                wire:model="enrollment_windows.{{ $index }}.accumulative_activities"
                            />
                            <span class="text-xs text-base-content/80">{{ __('ui.events.enrollment_window_accumulative') }}</span>
                        </label>
                    </div>
                    @if ($index > 0)
                        <div class="ml-auto flex shrink-0 justify-end self-end pb-1">
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
            </div>
        @endforeach
    </div>

    <x-button
        type="button"
        class="btn-outline btn-sm mt-2"
        wire:click="addEnrollmentWindow"
        :disabled="!$canAddEnrollmentWindow"
        wire:loading.attr="disabled"
    >
        {{ __('ui.events.enrollment_window_add') }}
    </x-button>

    <x-field-error :messages="$errors->get('enrollment_windows')" class="mt-2" />
</div>
