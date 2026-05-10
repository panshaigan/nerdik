@php
    $title = $event->name;
    $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $eventPlaceSummary = $event->compactPlaceSummary();
@endphp
<div
    class="space-y-2 sm:space-y-6"
    data-show-event-id="{{ $event->id }}"
    data-show-event-activity-ids='@json($attachedActivityIds)'
>
    <x-page-header :title="$title" :user="$event->creator" :organization="$event?->organization">
        <x-slot:subtitle>
            <div class="mb-1"><x-icon name="o-map-pin" />{{ $eventPlaceSummary }}</div>
            <div><x-icon name="o-calendar" />{{ $eventDateSummary }}</div>
        </x-slot:subtitle>

        <x-slot:titleSuffix>
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
        </x-slot:titleSuffix>
    </x-page-header>

    <div class="grid grid-cols-3 gap-3 pb-5 px-3 sm:px-0 sm:pb-6">
        <div class="box-glow-dark-primary rounded-2xl px-4 py-3">
            <x-stat
                title="{{ __('ui.events.confirmed_activities') }}"
                value="{{ $confirmedActivitiesCount }}"
                icon="o-envelope"
                class="ui-stat-embed"
            />
        </div>
        <div class="box-glow-dark-primary rounded-2xl px-4 py-3">
            <x-stat
                title="{{ __('ui.events.confirmed_participants') }}"
                value="{{ $confirmedParticipantsCount }}"
                icon="o-users"
                class="ui-stat-embed"
            />
        </div>
        <div
            class="box-glow-dark-primary rounded-2xl px-4 py-3 {{ auth()->check() ? 'relative overflow-hidden cursor-pointer select-none transition-transform duration-150 ease-out hover:box-glow-primary active:scale-[0.98]' : '' }}"
            @if (auth()->check())
                wire:click="{{ $hasInterest ? 'removeInterest' : 'addInterest' }}"
                wire:loading.class.delay="pointer-events-none cursor-wait"
                wire:target="addInterest, removeInterest"
            @endif
            data-ui="event-show-interested-stat"
        >
            <x-stat
                title="{{ __('ui.events.interested_people_count') }}"
                value="{{ $interestedPeopleCount }}"
                icon="{{ $hasInterest ? 's-star' : 'o-star' }}"
                color="{{ auth()->check() ? ($hasInterest ? 'text-warning' : 'text-base-content/80 hover:text-warning') : '' }}"
                class="ui-stat-embed"
            />
            @auth
                <div
                    wire:loading.delay
                    wire:target="addInterest, removeInterest"
                    class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-base-100/40"
                    aria-live="polite"
                >
                    <span class="loading loading-spinner loading-sm text-primary" aria-hidden="true"></span>
                </div>
            @endauth
        </div>
    </div>

    <div
        id="ui-event-show-hero"
        class="ui-event-show-hero ui-content-card relative min-h-[min(32rem,70dvh)] rounded-2xl"
    >
        <div
            wire:loading.delay.shortest
            wire:target="tab"
            class="absolute inset-0 z-30 flex cursor-wait items-center justify-center rounded-2xl bg-base-100/65 backdrop-blur-[2px]"
            aria-live="polite"
            role="status"
            data-ui="event-show-tab-loading"
        >
            <span class="sr-only">{{ __('ui.common.loading') }}</span>
            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
        </div>

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
                                    wire:click="openSlotCreateModal"
                                    wire:loading.attr="disabled"
                                    wire:target="openSlotCreateModal"
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
                                @if (($eventSignupPressureBlocksDelete ?? false))
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
                            @endif
                            </div>
                        @endif
                    </div>
                @endauth
            </x-slot:toolbar>

            <x-tab name="description" :label="__('ui.events.show_about')" class="!p-0" data-ui="event-show-tab-description" icon="o-document-text">
                @if ($tab === 'description')
                    <livewire:events.event-show-description-tab
                        defer
                        :event-id="$eventId"
                        :active-tab="$tab"
                        wire:key="event-desc-{{ $eventId }}"
                    />
                @endif
            </x-tab>

            <x-tab name="plan" :label="__('ui.events.show_plan')" class="!p-0" data-ui="event-show-tab-plan" icon="o-calendar-days">
                @if ($tab === 'plan')
                    <livewire:events.event-show-plan-tab
                        lazy
                        :event-id="$eventId"
                        :active-tab="$tab"
                        :attached-activity-ids="$attachedActivityIds"
                        :shell-interested-activity-ids="$interestedActivityIds"
                        wire:key="event-plan-{{ $eventId }}"
                    />
                @endif
            </x-tab>

            @if ($canManageEvent && $hasPendingProposals)
                <x-tab name="proposals" :label="__('ui.events.show_proposals')" class="!p-0" data-ui="event-show-tab-proposals" icon="o-clipboard-document-list">
                    @if ($tab === 'proposals')
                        <livewire:events.event-show-proposals-tab
                            lazy
                            :event-id="$eventId"
                            :active-tab="$tab"
                            wire:key="event-proposals-{{ $eventId }}"
                        />
                    @endif
                </x-tab>
            @endif

        </x-ui.tabs-with-toolbar>
    </div>

    @if ($slotCreateModalReady ?? false)
        @include('slots.partials.create-modal-shell', [
            'event' => $event,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotNameSuggestions' => $slotNameSuggestions ?? [],
        ])
    @endif
    @include('slots.partials.edit-modal-shell')
    @include('livewire.events.partials.activity-preview-modal')

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    >
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
