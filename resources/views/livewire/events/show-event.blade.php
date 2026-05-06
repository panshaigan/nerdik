@php
    $title = $event->name;
    $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $eventPlaceSummary = $event->compactPlaceSummary();
@endphp
<div class="pb-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-header title="{{ $title }}" class="!mb-0 px-4 py-3 sm:px-6" size="text-2xl sm:text-4xl" use-h1>
            <x-slot:title class="text-primary text-glow-primary">
                    <span>{{ $title }}</span>
            </x-slot:title>
            <x-slot:subtitle>

            </x-slot:subtitle>

            <x-slot:actions>
                @if ($event->creator)
                    <x-user-badge
                        :user="$event->creator"
                        size="md"
                        data-ui="activity-show-host"
                        title="Creator"
                    />
                @endif
            </x-slot:actions>
        </x-header>
        <x-ui.hr icon="o-academic-cap" double/>
    </div>


    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">

        <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="ui-glow-stat ui-info-tile ui-info-tile--secondary rounded-xl px-4 py-3">
                <x-stat
                    title="{{ 'Date' }}"
                    value="{{ $eventDateSummary }}"
                    icon="o-calendar"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
            <div class="ui-glow-stat ui-info-tile ui-info-tile--accent rounded-xl px-4 py-3">
                @if (filled($eventPlaceSummary))
                <x-stat
                    title="{{ 'Location' }}"
                    value="{{ $eventPlaceSummary }}"
                    icon="o-map-pin"
                    class="!bg-transparent !p-0 !shadow-none"
                />
                @endif
            </div>
        </div>

        @if ($event->isCancelled())
            <x-alert class="ui-glow-panel-alert mb-4 rounded-xl px-4 py-3" icon="o-exclamation-triangle">
                <div class="space-y-1">
                    <p class="text-xl">{{ __('ui.events.cancelled_badge') }}</p>
                    @if ($event->cancel_reason)
                        <p><strong>{{ __('ui.activities.cancel_reason_label') }}:</strong> {{ $event->cancel_reason }}</p>
                    @endif
                    <p class="opacity-80">
                        {{ __('ui.events.cancelled_meta', [
                            'who' => $event->canceller?->displayName() ?? __('ui.common.unknown_user'),
                            'when' => $event->cancelled_at ? format_datetime_in_user_tz($event->cancelled_at) : '—',
                        ]) }}
                    </p>
                </div>
            </x-alert>
        @endif

        <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="ui-glow-stat rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.confirmed_activities') }}"
                    value="{{ $confirmedActivitiesCount }}"
                    icon="o-envelope"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
            <div class="ui-glow-stat rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.confirmed_participants') }}"
                    value="{{ $confirmedParticipantsCount }}"
                    icon="o-users"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
            <div class="ui-glow-stat rounded-xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.events.interested_people_count') }}"
                    value="{{ $interestedPeopleCount }}"
                    icon="o-star"
                    class="!bg-transparent !p-0 !shadow-none"
                />
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 mt-4">
        <div id="ui-event-show-hero" class="ui-event-show-hero ui-glow-board overflow-hidden rounded-2xl" data-ui="event-show-hero">

            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="event-show-tabs"
            >
                <x-slot:toolbar>
                    @auth
                        <div class="flex shrink-0 items-center gap-1" data-ui="event-show-tabs-toolbar">
                            @if ($canManageEvent)
                                <div class="flex shrink-0 items-center gap-1">
                                    <x-button
                                        id="ui-event-show-create-slots"
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary ui-action ui-action-create-slots"
                                        onclick="document.getElementById('event-slots-create-modal')?.showModal()"
                                        :tooltip="__('ui.slots.create_slots')"
                                        :aria-label="__('ui.slots.create_slots')"
                                        data-ui="event-show-create-slots"
                                        icon="o-plus"
                                    />
                                <x-button
                                    :link="route('events.edit', $event)"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :tooltip="__('Edit')"
                                    :aria-label="__('Edit').': '.$event->name"
                                    data-ui="event-show-edit-open"
                                    icon="o-pencil"
                                />
                                @if (auth()->user()?->canCreateEvents())
                                    <x-button
                                        :link="route('events.create', ['duplicate' => $event->slug])"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
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
                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
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
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
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
