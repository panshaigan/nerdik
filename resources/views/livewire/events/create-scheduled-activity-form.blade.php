<div data-ui="schedule-activity-form-host">
    @auth
        <x-modal
            wire:model="modalOpen"
            :title="__('ui.events.schedule_activity_modal_title')"
            box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet !max-w-3xl w-full"
            class="backdrop-blur modal-bottom md:modal-end"
            separator
            data-ui="overlay-sheet schedule-activity-modal"
        >
            <form
                wire:submit.prevent="save"
                id="event-schedule-activity-form"
                wire:key="schedule-activity-form-body-{{ $formBootKey }}"
                class="min-h-0 space-y-6 pt-3 pb-2"
                data-schedule-activity-form
                data-slot-mass-form
                novalidate
                x-data
                x-init="$watch('$wire.modalOpen', (open) => { if (open) { $nextTick(() => window.initSlotMassForm?.(document.getElementById('event-schedule-activity-form'))) } })"
            >
                @php
                    $slotMassConfig = [
                        'eventDatetimeBounds' => [
                            (int) $event->id => $eventDatetimeBounds,
                        ],
                    ];
                @endphp
                <script type="application/json" data-slot-mass-config>@json($slotMassConfig)</script>
                <script type="application/json" data-schedule-activity-rooms>@json($slotMassRoomsByVenueId)</script>

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
                        wire:model="activity_type_id"
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
                    <div data-schedule-activity-field="starts_at">
                        <x-input
                            wire:model="starts_at"
                            :label="__('ui.slots.starts_at_optional')"
                            :placeholder="__('ui.slots.starts_at_optional')"
                            type="text"
                            :step="$datetimeMinuteStepSeconds"
                            error-field="starts_at"
                            required
                            data-ui-datetime
                            icon="o-calendar-days"
                            inline
                            autocomplete="off"
                            inputmode="none"
                        />
                    </div>

                    <div data-schedule-activity-field="ends_at">
                        <x-input
                            wire:model="ends_at"
                            :label="__('ui.slots.ends_at_optional')"
                            :placeholder="__('ui.slots.ends_at_optional')"
                            type="text"
                            :step="$datetimeMinuteStepSeconds"
                            error-field="ends_at"
                            required
                            data-ui-datetime
                            icon="o-calendar-days"
                            inline
                            autocomplete="off"
                            inputmode="none"
                        />
                    </div>
                </div>

                <div data-ui="schedule-activity-place-root" data-slot-mass-place-root>
                    @if ($slotMassVenues->isEmpty())
                        <p class="text-sm text-base-content/70">{{ __('ui.slots.no_places_on_event') }}</p>
                    @else
                        <div class="grid grid-cols-1 gap-4 overflow-visible lg:grid-cols-2 lg:items-start">
                            <div class="min-w-0 overflow-visible" data-schedule-activity-venue-select>
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
                                        wire:model="venue_place_id"
                                        id="schedule_activity_venue_place_id"
                                        :label="__('ui.slots.venue_optional')"
                                        :options="$slotMassVenues
                                            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name.' ('.$v->type.')'])
                                            ->values()
                                            ->all()"
                                        inline
                                        data-slot-venue-select
                                    />
                                @endif
                                <x-field-error :messages="$errors->get('venue_place_id')" class="mt-2" />
                            </div>

                            <div class="min-w-0 overflow-visible" data-slot-room-block>
                                <div class="relative overflow-visible" data-slot-room-input>
                                    <x-input
                                        wire:model="new_room_name"
                                        :label="__('ui.slots.room_optional')"
                                        :placeholder="__('ui.slots.room_optional')"
                                        type="text"
                                        error-field="new_room_name"
                                        autocomplete="off"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="schedule-activity-room-suggestions-popup"
                                        inline
                                    />
                                    <div
                                        id="schedule-activity-room-suggestions-popup"
                                        class="fixed z-[9999] hidden max-h-[min(14rem,50vh)] overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                                        data-slot-room-popup
                                        role="listbox"
                                    ></div>
                                </div>
                                <x-field-error :messages="$errors->get('new_room_name')" class="mt-2" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="ui-tile-empty min-w-0 rounded-2xl p-4 sm:p-6">
                    @include('livewire.activities.partials.manage-participation-rules-tab')
                </div>
            </form>

            <x-slot:actions>
                <x-button type="button" class="btn-outline" wire:click="closeModal" wire:loading.attr="disabled">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button
                    class="btn-primary"
                    type="submit"
                    form="event-schedule-activity-form"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    spinner="save"
                >
                    <span wire:loading.remove wire:target="save">{{ __('ui.events.schedule_activity_submit') }}</span>
                    <span wire:loading wire:target="save">{{ __('ui.common.saving') }}</span>
                </x-button>
            </x-slot:actions>
        </x-modal>
    @endauth
</div>
