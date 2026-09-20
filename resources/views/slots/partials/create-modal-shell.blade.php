@auth
    <dialog
        id="event-slots-create-modal"
        class="modal backdrop-blur modal-bottom md:modal-end"
        data-ui="overlay-sheet"
    >
        <div class="modal-box max-w-3xl ui-modal-surface ui-overlay-shell ui-overlay-sheet">
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

            <div class="!mb-0 pr-10">
                <h3 class="text-xl font-semibold leading-tight text-base-content">
                    {{ __('ui.slots.create_slots') }}
                </h3>
                <hr class="mt-3 border-t-[length:var(--border)] border-base-content/10" />
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @include('slots.mass-create', [
                    'embeddedInModal' => true,
                    'externalModalChrome' => true,
                    'editMode' => false,
                    'lockedEvent' => $event,
                    'slotMassVenues' => $slotMassVenues ?? collect(),
                    'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId ?? [],
                    'slotBaseNameSuggestions' => $slotBaseNameSuggestions ?? [],
                    'slotNameSuggestions' => $slotNameSuggestions ?? [],
                    'massFormAction' => route('events.slots.mass', $event),
                ])
            </div>

            <div class="modal-action">
                <x-button type="button" class="btn-outline" onclick="this.closest('dialog')?.close()">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button
                    class="btn-primary"
                    type="submit"
                    form="event-slots-mass-create-form"
                >
                    {{ __('ui.slots.create_slots') }}
                </x-button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <x-button type="submit" class="btn-ghost" :aria-label="__('ui.common.cancel')">{{ __('ui.common.cancel') }}</x-button>
        </form>
    </dialog>
@endauth
