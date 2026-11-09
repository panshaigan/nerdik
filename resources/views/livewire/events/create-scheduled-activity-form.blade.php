<div>
    @auth
        <dialog
        id="event-schedule-activity-modal"
        class="modal backdrop-blur modal-bottom md:modal-end"
        data-ui="overlay-sheet"
    >
        <div class="modal-box max-w-3xl ui-modal-surface ui-overlay-shell ui-overlay-sheet flex max-h-[min(92dvh,48rem)] flex-col overflow-hidden">
            <form method="dialog" tabindex="-1">
                <button
                    type="submit"
                    class="btn btn-circle btn-sm btn-ghost absolute end-2 top-2 z-[999]"
                    aria-label="{{ __('ui.common.close') }}"
                    tabindex="-1"
                >
                    <x-mary-icon name="o-x-mark" class="h-4 w-4" />
                </button>
            </form>

            <div class="shrink-0 !mb-0 pr-10">
                <h3 class="text-xl font-semibold leading-tight text-base-content">
                    {{ __('ui.events.schedule_activity_modal_title') }}
                </h3>
                <hr class="mt-3 border-t-[length:var(--border)] border-base-content/10" />
            </div>

            <form
                wire:submit.prevent="save"
                id="event-schedule-activity-form"
                class="flex min-h-0 flex-1 flex-col overflow-hidden"
                data-ui="event-schedule-activity-form"
                novalidate
            >
                <div
                    class="min-h-0 flex-1 overflow-y-auto overflow-x-visible py-4 space-y-6"
                    data-ui="overlay-sticky-tabs"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input
                            wire:model="name"
                            :label="__('ui.activities.name')"
                            :placeholder="__('ui.activities.name')"
                            type="text"
                            error-field="name"
                            required
                            autocomplete="off"
                            icon="o-bookmark"
                            inline
                        />

                        <x-select
                            wire:model.live="activity_type_id"
                            :label="__('ui.activities.type')"
                            error-field="activity_type_id"
                            required
                            :options="$activityTypes->map(fn ($type) => [
                                'id' => $type->id,
                                'name' => __('ui.activities.types.'.$type->slug),
                            ])->values()->all()"
                            :placeholder="__('ui.activities.choose_type')"
                            placeholder-value=""
                            icon="o-squares-2x2"
                            inline
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input
                            wire:model="starts_at"
                            :label="__('ui.slots.starts_at_optional')"
                            :placeholder="__('ui.slots.starts_at_optional')"
                            type="text"
                            :step="$datetimeMinuteStepSeconds"
                            error-field="starts_at"
                            required
                            data-ui-datetime
                            data-schedule-activity-starts
                            data-event-datetime-min="{{ $eventDatetimeBounds['starts_at'] ?? '' }}"
                            data-event-datetime-max="{{ $eventDatetimeBounds['ends_at'] ?? '' }}"
                            inline
                            autocomplete="off"
                            inputmode="none"
                        />

                        <x-input
                            wire:model="ends_at"
                            :label="__('ui.slots.ends_at_optional')"
                            :placeholder="__('ui.slots.ends_at_optional')"
                            type="text"
                            :step="$datetimeMinuteStepSeconds"
                            error-field="ends_at"
                            required
                            data-ui-datetime
                            data-schedule-activity-ends
                            data-event-datetime-min="{{ $eventDatetimeBounds['starts_at'] ?? '' }}"
                            data-event-datetime-max="{{ $eventDatetimeBounds['ends_at'] ?? '' }}"
                            inline
                            autocomplete="off"
                            inputmode="none"
                        />
                    </div>

                    <div data-ui="schedule-activity-place-root">
                        @if ($slotMassVenues->isEmpty())
                            <p class="text-sm text-base-content/70">{{ __('ui.slots.no_places_on_event') }}</p>
                        @else
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
                                <div class="min-w-0 overflow-visible">
                                    @if ($singleVenueLocked)
                                        <input type="hidden" wire:model="venue_place_id" />
                                        <x-select
                                            :label="__('ui.slots.venue_optional')"
                                            :options="[[
                                                'id' => $slotMassVenues->first()->id,
                                                'name' => $slotMassVenues->first()->name,
                                            ]]"
                                            inline
                                            disabled
                                        />
                                    @else
                                        <x-select
                                            wire:model.live="venue_place_id"
                                            id="schedule_activity_venue_place_id"
                                            :label="__('ui.slots.venue_optional')"
                                            :options="$slotMassVenues
                                                ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name.' ('.$v->type.')'])
                                                ->values()
                                                ->all()"
                                            inline
                                        />
                                    @endif
                                    <x-field-error :messages="$errors->get('venue_place_id')" class="mt-2" />
                                </div>

                                <div class="min-w-0 overflow-visible">
                                    <x-input
                                        wire:model="new_room_name"
                                        :label="__('ui.slots.room_optional')"
                                        :placeholder="__('ui.slots.room_optional')"
                                        type="text"
                                        error-field="new_room_name"
                                        autocomplete="off"
                                        inline
                                    />
                                    @if ($venue_place_id && ! empty($slotMassRoomsByVenueId[$venue_place_id] ?? []))
                                        <p class="mt-2 text-xs text-base-content/60">
                                            {{ collect($slotMassRoomsByVenueId[$venue_place_id])->pluck('name')->join(', ') }}
                                        </p>
                                    @endif
                                    <x-field-error :messages="$errors->get('new_room_name')" class="mt-2" />
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="ui-tile-empty min-w-0 rounded-2xl p-4 sm:p-6">
                        @include('livewire.activities.partials.manage-participation-rules-tab')
                    </div>
                </div>

                <div class="modal-action shrink-0 border-t border-base-300 pt-4">
                    <x-button type="button" class="btn-outline" onclick="this.closest('dialog')?.close()">
                        {{ __('ui.common.cancel') }}
                    </x-button>
                    <x-button
                        class="btn-primary"
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        spinner="save"
                    >
                        <span wire:loading.remove wire:target="save">{{ __('ui.events.schedule_activity_submit') }}</span>
                        <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
                    </x-button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <x-button type="submit" class="btn-ghost" :aria-label="__('ui.common.close')">{{ __('ui.common.cancel') }}</x-button>
        </form>
        </dialog>
    @endauth
</div>
