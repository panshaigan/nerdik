@php
    $embeddedInModal = $embeddedInModal ?? false;
    $editMode = $editMode ?? false;
    $slot = $slot ?? null;
    $slotVenueRoomDefaults = $slotVenueRoomDefaults ?? ['venue_place_id' => null, 'room_name' => null];
    $defaultVenuePlaceId = old('venue_place_id', $slotVenueRoomDefaults['venue_place_id']);
    $slotMassVenues = $slotMassVenues ?? collect();
    if ($slotMassVenues->isNotEmpty() && ($defaultVenuePlaceId === null || $defaultVenuePlaceId === '')) {
        $defaultVenuePlaceId = $slotMassVenues->first()->id;
    }
    $defaultRoomName = old('new_room_name', $slotVenueRoomDefaults['room_name'] ?? '');
    $defaultEventId = ($editMode && $slot) ? $slot->event_id : null;
    $countDefault = $countDefault ?? 5;
    $activityTypes = \App\Models\ActivityType::query()->orderBy('id')->get(['id', 'slug']);
    $oldActivityTypeIds = old('activity_types', $editMode && $slot ? $slot->activity_types : []);
    if (! is_array($oldActivityTypeIds)) {
        $oldActivityTypeIds = [];
    }
    $oldActivityTypeIds = array_values(array_filter(array_map('intval', $oldActivityTypeIds), fn ($id) => $id > 0));
    $slotBaseNameSuggestions = $slotBaseNameSuggestions ?? [];
    $slotNameSuggestions = $slotNameSuggestions ?? [];
    $slotMassRoomsByVenueId = $slotMassRoomsByVenueId ?? [];
    $eventVenuesByEventId = $eventVenuesByEventId ?? [];
    $roomsByEventAndVenue = $roomsByEventAndVenue ?? [];
    $lockedEvent = $lockedEvent ?? null;
    $singleVenueLocked = $lockedEvent && $slotMassVenues->count() === 1;
    $slotMassConfig = [
        'oldVenuePlaceId' => $defaultVenuePlaceId !== null && $defaultVenuePlaceId !== '' ? (int) $defaultVenuePlaceId : null,
        'isEdit' => $editMode,
        'initialActivityTypes' => $oldActivityTypeIds,
        'activityTypeLabels' => $activityTypes->mapWithKeys(fn ($type) => [$type->id => __('ui.activities.types.'.$type->slug)])->all(),
        'strings' => [
            'none' => __('ui.common.none'),
        ],
    ];
    $defaultRequiresApproval = ($editMode && $slot) ? (bool) $slot->requires_approval : true;
    $requiresApprovalChecked = filter_var(
        old('requires_approval', $defaultRequiresApproval ? '1' : '0'),
        FILTER_VALIDATE_BOOLEAN
    );
    $approvalFieldId = $editMode ? 'requires_approval_modal_edit' : 'requires_approval_modal_create';
    $massFormAction = $massFormAction ?? null;
    $formAction = $massFormAction
        ?? ($editMode
            ? route('slots.update', $slot)
            : ($lockedEvent ? route('events.slots.mass', $lockedEvent) : '#'));
@endphp

<form
    method="POST"
    action="{{ $formAction }}"
    class="space-y-0"
    data-slot-mass-form
    @if ($editMode) data-slot-edit-form @endif
    @if ($massFormAction) data-event-show-async-mass @endif
    @if ($embeddedInModal && $editMode) data-slot-async-submit @endif
>
    @csrf
    @if (! empty($massFormAction) || ($embeddedInModal && $editMode))
        <div data-slot-form-errors role="alert" class="alert alert-error mb-3 hidden text-sm"></div>
    @endif
    <input type="hidden" name="requires_approval" value="0">
    @if ($editMode)
        @method('PUT')
    @else
        <input type="hidden" name="mass" value="1">
    @endif

    @if ($lockedEvent)
        <input type="hidden" name="event_id" value="{{ $lockedEvent->id }}" />
        @if ($embeddedInModal)
            <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
        @elseif (! $editMode)
            <input type="hidden" name="redirect_to_event_slug" value="{{ $lockedEvent->slug }}" />
        @endif
    @endif

    <div class="space-y-4">
        @if ($embeddedInModal)
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h3
                    class="text-lg font-semibold leading-tight text-base-content"
                    @if ($editMode) id="slot-edit-modal-title" @endif
                >
                    {{ $editMode ? __('ui.events.edit_slot') : __('ui.slots.create_slots') }}
                </h3>
                <div class="flex max-w-[min(100%,22rem)] flex-col items-end gap-1 text-end">
                    <div class="flex items-center gap-2">
                        <input
                            id="{{ $approvalFieldId }}"
                            name="requires_approval"
                            type="checkbox"
                            value="1"
                            class="checkbox checkbox-sm"
                            @checked($requiresApprovalChecked)
                        />
                        <label for="{{ $approvalFieldId }}" class="label cursor-pointer text-sm text-base-content">{{ __('ui.slots.requires_approval') }}</label>
                    </div>
                </div>
            </div>
        @endif

        @if (! $lockedEvent)
            <div>
                <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.slots.event') }}</p>
                <select id="event_id" name="event_id" class="select select-bordered mt-1 w-full" required data-slot-mass-event-select>
                    @foreach ($events as $ev)
                        <option value="{{ $ev->id }}"
                            @selected((string) old('event_id', $defaultEventId ?? '') === (string) $ev->id)>
                            {{ $ev->name }} · {{ format_in_user_tz($ev->starts_at, 'Y-m-d H:i') }}
                        </option>
                    @endforeach
                </select>
                <x-field-error :messages="$errors->get('event_id')" class="mt-2" />
            </div>
        @endif

        @if ($editMode)
            <div class="relative">
                <x-input
                    label="{{ __('ui.activities.name') }}"
                    name="name"
                    type="text"
                    value="{{ old('name', $slot->name) }}"
                    error-field="name"
                    required
                    autocomplete="off"
                    data-slot-name-input
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="slot-edit-name-suggestions-popup"
                />
                <div
                    id="slot-edit-name-suggestions-popup"
                    class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                    data-slot-name-popup
                    role="listbox"
                ></div>
                <script type="application/json" data-slot-name-suggestions-json>@json($slotNameSuggestions)</script>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="relative sm:col-span-1">
                    <x-input
                        label="{{ __('ui.slots.base_name') }}"
                        name="base_name"
                        type="text"
                        value="{{ old('base_name') }}"
                        error-field="base_name"
                        required
                        autocomplete="off"
                        data-slot-base-name-input
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="slot-base-name-suggestions-popup"
                    />
                    <div
                        id="slot-base-name-suggestions-popup"
                        class="absolute left-0 right-0 z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                        data-slot-base-name-popup
                        role="listbox"
                    ></div>
                    <script type="application/json" data-slot-base-name-suggestions-json>@json($slotBaseNameSuggestions)</script>
                </div>

                <div>
                    <x-input
                        label="{{ __('ui.slots.count') }}"
                        name="count"
                        type="number"
                        min="1"
                        max="100"
                        value="{{ old('count', $countDefault) }}"
                        error-field="count"
                        required
                    />
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input
                        label="{{ __('ui.slots.starts_at_optional') }}"
                        name="starts_at"
                        type="datetime-local"
                        value="{{ old('starts_at', $editMode && $slot && $slot->starts_at ? format_in_user_tz($slot->starts_at, 'Y-m-d\TH:i') : '') }}"
                        error-field="starts_at"
                    />
                </div>
                <div>
                    <x-input
                        label="{{ __('ui.slots.ends_at_optional') }}"
                        name="ends_at"
                        type="datetime-local"
                        value="{{ old('ends_at', $editMode && $slot && $slot->ends_at ? format_in_user_tz($slot->ends_at, 'Y-m-d\TH:i') : '') }}"
                        error-field="ends_at"
                    />
                </div>
            </div>
            <div @class([
                'grid grid-cols-1 gap-4',
                'sm:grid-cols-2 sm:items-end' => ! $embeddedInModal,
            ])>
                <div>
                    <x-input
                        label="{{ __('ui.slots.max_capacity_optional') }}"
                        name="max_capacity"
                        type="number"
                        min="1"
                        value="{{ old('max_capacity', $editMode && $slot ? $slot->max_capacity : '') }}"
                        error-field="max_capacity"
                    />
                </div>
                @unless ($embeddedInModal)
                    <div class="flex flex-col gap-1 pb-0.5 sm:pb-1">
                        <div class="flex items-center gap-2">
                            <input id="requires_approval" name="requires_approval" type="checkbox" value="1" class="checkbox checkbox-sm"
                                   @checked($requiresApprovalChecked) />
                            <label for="requires_approval" class="label cursor-pointer text-sm text-base-content">{{ __('ui.slots.requires_approval') }}</label>
                        </div>
                    </div>
                @endunless
            </div>
        </div>

        <div data-slot-activity-types-root>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-1 font-medium">{{ __('ui.slots.activity_types') }}</legend>
                <p class="mb-2 text-xs text-base-content/70">{{ __('ui.slots.activity_types_help') }}</p>

                <div class="relative z-10">
                    <select data-slot-activity-add class="select select-bordered w-full">
                        <option value="">{{ __('ui.slots.add_activity_type') }}</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type->id }}">{{ __('ui.activities.types.'.$type->slug) }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-slot-activity-chips class="mt-2 flex min-h-[1.5rem] flex-wrap gap-2"></div>
                <div data-slot-activity-hidden class="hidden"></div>
            </fieldset>
            <x-field-error :messages="$errors->get('activity_types')" class="mt-2" />
            <x-field-error :messages="$errors->get('activity_types.*')" class="mt-2" />
        </div>

        <div class="border-t border-base-300 pt-4 overflow-visible" data-slot-mass-place-root>
            @if ($lockedEvent && $slotMassVenues->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('ui.slots.no_places_on_event') }}</p>
            @else
                <div class="grid grid-cols-1 gap-4 overflow-visible lg:grid-cols-2 lg:items-start">
                    <div class="min-w-0 overflow-visible">
                        <fieldset class="fieldset py-0">
                            <legend class="fieldset-legend mb-0.5">{{ __('ui.slots.venue_optional') }}</legend>
                            <p class="mb-2 text-xs text-base-content/70">{{ __('ui.slots.venue_help') }}</p>

                            @if ($singleVenueLocked)
                                <input
                                    type="hidden"
                                    name="venue_place_id"
                                    value="{{ $slotMassVenues->first()->id }}"
                                    data-slot-venue-id
                                />
                                <select class="select select-bordered w-full" disabled>
                                    <option selected>{{ $slotMassVenues->first()->name }}</option>
                                </select>
                            @elseif ($lockedEvent && $slotMassVenues->isNotEmpty())
                                <select
                                    name="venue_place_id"
                                    id="venue_place_id"
                                    class="select select-bordered w-full"
                                    data-slot-venue-select
                                >
                                    @foreach ($slotMassVenues as $v)
                                        <option value="{{ $v->id }}" @selected((string) $defaultVenuePlaceId === (string) $v->id)>
                                            {{ $v->name }} ({{ $v->type }})
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <select
                                    name="venue_place_id"
                                    id="venue_place_id"
                                    class="select select-bordered w-full"
                                    data-slot-venue-select
                                >
                                    <option value="">{{ __('ui.common.none') }}</option>
                                </select>
                            @endif
                            <x-field-error :messages="$errors->get('venue_place_id')" class="mt-2" />
                        </fieldset>
                    </div>

                    @if (($lockedEvent && $slotMassVenues->isNotEmpty()) || ! $lockedEvent)
                        <div class="min-w-0 overflow-visible" data-slot-room-block>
                            <p class="fieldset-legend mb-0.5">{{ __('ui.slots.room_optional') }}</p>
                            <p class="mb-2 text-xs text-base-content/70">{{ __('ui.slots.room_help') }}</p>
                            <div class="relative overflow-visible">
                                <x-input
                                    label=""
                                    name="new_room_name"
                                    type="text"
                                    value="{{ $defaultRoomName }}"
                                    error-field="new_room_name"
                                    autocomplete="off"
                                    placeholder="{{ __('ui.slots.room_placeholder') }}"
                                    data-slot-room-input
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="slot-room-suggestions-popup"
                                />
                                <div
                                    id="slot-room-suggestions-popup"
                                    class="fixed z-[9999] hidden max-h-[min(14rem,50vh)] overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                                    data-slot-room-popup
                                    role="listbox"
                                ></div>
                            </div>
                            <x-field-error :messages="$errors->get('new_room_name')" class="mt-2" />
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <script type="application/json" data-slot-mass-config>@json($slotMassConfig)</script>
        <script type="application/json" data-slot-mass-event-venues>@json($eventVenuesByEventId)</script>
        <script type="application/json" data-slot-mass-rooms>@json($roomsByEventAndVenue)</script>
        @if ($lockedEvent)
            <script type="application/json" data-slot-mass-rooms-locked>@json($slotMassRoomsByVenueId)</script>
        @endif
    </div>

    <div class="mt-6 flex justify-end gap-3">
        @if ($embeddedInModal)
            <x-button type="button" class="btn-outline" onclick="this.closest('dialog')?.close()">
                {{ __('ui.common.cancel') }}
            </x-button>
        @else
            <x-button
                :link="$lockedEvent ? route('events.show', $lockedEvent) : route('dashboard')"
                class="btn-outline"
            >{{ __('ui.common.cancel') }}</x-button>
        @endif

        <x-button class="btn-primary" type="submit">{{ $editMode ? __('ui.common.save') : __('ui.slots.create_slots') }}</x-button>
    </div>
</form>
