@php
    $title = $event->name;
    $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $eventPlaceSummary = $event->compactPlaceSummary();
    $attachedActivityIds = $event->slots
        ->pluck('activity_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();
@endphp
<div
    class="pb-6"
    data-show-event-id="{{ $event->id }}"
    data-show-event-activity-ids='@json($attachedActivityIds)'
>
    <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
        <x-header title="{{ $title }}" class="!mb-0 px-6 py-3 sm:px-10" size="text-3xl sm:text-4xl" use-h1>
            <x-slot:title class="text-primary text-glow-primary">
                <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>{{ $title }}</span>
                    @if ($event->isCancelled())
                        <x-popover class="inline-flex transition-none" position="bottom" offset="8">
                            <x-slot:trigger>
                                <x-badge
                                    :value="__('ui.events.cancelled_short')"
                                    icon="o-x-circle"
                                    class="badge-warning badge-sm shrink-0 font-semibold normal-case"
                                    data-ui="event-show-cancelled-badge"
                                    :title="__('ui.events.cancelled_badge')"
                                />
                            </x-slot:trigger>
                            <x-slot:content class="max-w-sm text-sm text-base-content">
                                <div class="space-y-2">
                                    @if (filled($event->cancel_reason))
                                        <p>
                                            <span class="font-semibold">{{ __('ui.activities.cancel_reason_label') }}:</span>
                                            <span class="mt-0.5 block">{{ $event->cancel_reason }}</span>
                                        </p>
                                    @endif
                                    <p>
                                        <span class="font-semibold">{{ __('ui.events.cancellation_popover_who') }}:</span>
                                        <span class="mt-0.5 block">{{ $event->canceller?->displayName() ?? __('ui.common.unknown_user') }}</span>
                                    </p>
                                    <p>
                                        <span class="font-semibold">{{ __('ui.events.cancellation_popover_when') }}:</span>
                                        <span class="mt-0.5 block">{{ $event->cancelled_at ? format_datetime_in_user_tz($event->cancelled_at) : '—' }}</span>
                                    </p>
                                </div>
                            </x-slot:content>
                        </x-popover>
                    @endif
                </span>
            </x-slot:title>
            <x-slot:subtitle>
                <div class="mb-1"><x-icon name="o-map-pin" />{{$eventPlaceSummary}}</div>
                <div><x-icon name="o-calendar" />{{$eventDateSummary}}</div>
            </x-slot:subtitle>

            <x-slot:actions>
                @if ($event->creator)
                    <x-user-badge
                        :user="$event->creator"
                        :organization="$event?->organization"
                        size="md"
                        data-ui="activity-show-host"
                        title="Creator"
                        class=""
                    />
                @endif
            </x-slot:actions>
        </x-header>
        <x-ui.hr icon="o-academic-cap" class="mt-1" double/>
    </div>


    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="mb-6 grid gap-3 grid-cols-3 px-3 sm:px-0">
            <div class="box-glow-dark-primary rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.confirmed_activities') }}"
                    value="{{ $confirmedActivitiesCount }}"
                    icon="o-envelope"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
            <div class="box-glow-dark-primary rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.confirmed_participants') }}"
                    value="{{ $confirmedParticipantsCount }}"
                    icon="o-users"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
            <div class="box-glow-dark-primary rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.interested_people_count') }}"
                    value="{{ $interestedPeopleCount }}"
                    icon="o-star"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 mt-4 ">
        <div class="ui-content-card rounded-2xl">

            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="event-show-tabs"
                class="bg-texture-scratches rounded-2xl"
            >
                <x-slot:toolbar>
                    @auth
                        <div class="flex shrink-0 items-center gap-1" data-ui="event-show-tabs-toolbar">
                            @if ($canManageEvent)
                                <div class="flex shrink-0 items-center gap-1">
                                    <x-button
                                        id="ui-event-show-create-slots"
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success ui-action ui-action-create-slots"
                                        onclick="document.getElementById('event-slots-create-modal')?.showModal()"
                                        :tooltip="__('ui.slots.create_slots')"
                                        :aria-label="__('ui.slots.create_slots')"
                                        data-ui="event-show-create-slots"
                                        icon="o-plus"
                                    />
                                <x-button
                                    :link="route('events.edit', $event)"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-secondary"
                                    :tooltip="__('Edit')"
                                    :aria-label="__('Edit').': '.$event->name"
                                    data-ui="event-show-edit-open"
                                    icon="o-pencil"
                                />
                                @if (auth()->user()?->canCreateEvents())
                                    <x-button
                                        :link="route('events.create', ['duplicate' => $event->slug])"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-accent"
                                        :tooltip="__('ui.events.duplicate_action')"
                                        :aria-label="__('ui.events.duplicate_action').': '.$event->name"
                                        data-ui="event-show-duplicate-open"
                                        icon="o-square-2-stack"
                                    />
                                @endif
                                @if (! $event->isCancelled())
                                    @if (($eventSignupPressureBlocksDelete ?? false))
                                        <x-button
                                            type="button"
                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                            wire:click="confirmCancelEvent"
                                            :tooltip="__('ui.events.cancel_action')"
                                            :aria-label="__('ui.events.cancel_action').': '.$event->name"
                                            data-ui="event-show-cancel-event"
                                            icon="o-x-circle"
                                        />
                                    @else
                                        <x-button
                                            type="button"
                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                            wire:click="confirmDeleteEvent"
                                            :tooltip="__('Delete')"
                                            :aria-label="__('Delete').': '.$event->name"
                                            data-ui="event-show-delete"
                                            icon="o-trash"
                                        />
                                    @endif
                                @else
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-info"
                                        wire:click="confirmReopenEvent"
                                        :tooltip="__('ui.events.reopen_action')"
                                        :aria-label="__('ui.events.reopen_action')"
                                        data-ui="event-show-reopen-event"
                                        icon="o-arrow-uturn-left"
                                    />
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                        wire:click="confirmDeleteEvent"
                                        :tooltip="__('Delete')"
                                        :aria-label="__('Delete').': '.$event->name"
                                        data-ui="event-show-delete-after-cancel"
                                        icon="o-trash"
                                    />
                                @endif
                                </div>
                            @endif

                            @if ($hasInterest)
                                <x-button
                                    type="button"
                                    wire:click="removeInterest"
                                    class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                    :tooltip="__('ui.interests.remove_from_interests')"
                                    data-ui="event-show-interest-remove"
                                    icon="s-star"
                                />
                            @else
                                <x-button
                                    type="button"
                                    wire:click="addInterest"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                    :tooltip="__('ui.interests.add_to_interests')"
                                    data-ui="event-show-interest-add"
                                    icon="o-star"
                                />
                            @endif
                        </div>
                    @endauth
                </x-slot:toolbar>

                <x-tab name="description" :label="__('ui.events.show_about')" class="!p-0" data-ui="event-show-tab-description" icon="o-document-text">
                    @include('livewire.events.partials.show-description-tab')
                </x-tab>

                <x-tab name="plan" :label="__('ui.events.show_plan')" class="!p-0" data-ui="event-show-tab-plan" icon="o-calendar-days">
                    @include('livewire.events.partials.show-plan-tab')
                </x-tab>

                @if ($canManageEvent && $pendingProposals->isNotEmpty())
                    <x-tab name="proposals" :label="__('ui.events.show_proposals')" class="!p-0" data-ui="event-show-tab-proposals" icon="o-clipboard-document-list">
                        @include('livewire.events.partials.show-proposals-tab')
                    </x-tab>
                @endif

            </x-ui.tabs-with-toolbar>
        </div>

        @include('slots.partials.create-modal-shell', [
            'event' => $event,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
        ])
        @include('slots.partials.edit-modal-shell')
        @include('livewire.events.partials.activity-preview-modal')

        <x-ui.confirm-modal
            wire:model="confirmModalOpen"
            :title="$confirmModalTitle"
            :message="$confirmModalMessage"
            confirm-action="runConfirmedAction"
        >
            @if ($pendingAction === 'cancel_slot_activity' && $pendingContextId !== null)
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">{{ __('ui.activities.cancel_reason_label') }}</span>
                    </label>
                    <textarea
                        class="textarea textarea-bordered w-full"
                        rows="4"
                        wire:model.defer="slotCancelReason.{{ (int) $pendingContextId }}"
                    ></textarea>
                    @error('slotCancelReason.'.$pendingContextId)
                        <div class="mt-2 text-xs text-error">{{ $message }}</div>
                    @enderror
                </div>
            @endif
            @if ($pendingAction === 'cancel_event')
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">{{ __('ui.activities.cancel_reason_label') }}</span>
                    </label>
                    <textarea
                        class="textarea textarea-bordered w-full"
                        rows="4"
                        wire:model.defer="eventCancelReason"
                    ></textarea>
                    @error('eventCancelReason')
                        <div class="mt-2 text-xs text-error">{{ $message }}</div>
                    @enderror
                </div>
            @endif
        </x-ui.confirm-modal>
    </div>
</div>
