@php
    $title = $event->name;
    $eventDateSummary = format_date_range_compact($event->starts_at, $event->ends_at);
    $eventPlaceSummary = $event->compactPlaceSummary();
@endphp
<div
    class="relative"
    data-show-event-id="{{ $event->id }}"
    data-show-event-activity-ids='@json($attachedActivityIds)'
>
    <x-listing-show-page-background
        :picture="$coverPicture"
        data-ui="event-show-page-background"
    />

    <div class="relative z-0 space-y-4 sm:space-y-6">
    <x-page-header :title="$title" :user="$event->creator" :organization="$event?->organization">
        <x-slot:titlePrefix>
            @if ($previousInSeries)
                <x-popover class="inline-flex transition-none" position="bottom" offset="8">
                    <x-slot:trigger>
                        <a
                            href="{{ route('events.show', $previousInSeries) }}"
                            wire:navigate
                            @class([
                                'btn btn-ghost btn-square btn-sm shrink-0 text-base-content/70 hover:text-base-content',
                                'opacity-50' => $previousInSeries->isCancelled(),
                            ])
                            aria-label="{{ __('ui.events.series_previous') }}: {{ $previousInSeries->name }}"
                            data-ui="event-show-series-prev"
                        >
                            <x-icon name="o-chevron-left" class="h-6 w-6" />
                        </a>
                    </x-slot:trigger>
                    <x-slot:content class="!w-auto max-w-xs whitespace-normal text-sm text-base-content">
                        {{ $previousInSeries->name }}
                    </x-slot:content>
                </x-popover>
            @endif
        </x-slot:titlePrefix>

        <x-slot:subtitle>
            <div class="mb-1">
                <x-icon name="o-map-pin" class="inline h-4 w-4 align-text-bottom" />
                <x-browse.place-search-links
                    :places="\App\Support\Browse\BrowseSearchUrl::eventPlaceLinks($event)"
                    :fallback="$eventPlaceSummary"
                    link-class="link link-primary text-glow-base-100"
                />
            </div>
            <div class="">
                <x-icon name="o-calendar" class="inline h-4 w-4 align-text-bottom" />{{ $eventDateSummary }}
                @if ($event->eventSeries)
                    <span class="ml-2" data-ui="event-show-series-link">
                        <x-icon name="o-rectangle-stack" class="inline h-4 w-4 align-text-bottom" />
                        <a
                            href="{{ route('event-series.show', $event->eventSeries) }}"
                            wire:navigate
                            class="link link-primary text-glow-base-100"
                        >{{ __('ui.events.series_link', ['name' => $event->eventSeries->name]) }}</a>
                    </span>
                @endif
            </div>
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
            @if ($nextInSeries)
                <x-popover class="inline-flex transition-none" position="bottom" offset="8">
                    <x-slot:trigger>
                        <a
                            href="{{ route('events.show', $nextInSeries) }}"
                            wire:navigate
                            @class([
                                'btn btn-ghost btn-square btn-sm shrink-0 text-base-content/70 hover:text-base-content',
                                'opacity-50' => $nextInSeries->isCancelled(),
                            ])
                            aria-label="{{ __('ui.events.series_next') }}: {{ $nextInSeries->name }}"
                            data-ui="event-show-series-next"
                        >
                            <x-icon name="o-chevron-right" class="h-6 w-6" />
                        </a>
                    </x-slot:trigger>
                    <x-slot:content class="!w-auto max-w-xs whitespace-normal text-sm text-base-content">
                        {{ $nextInSeries->name }}
                    </x-slot:content>
                </x-popover>
            @endif
        </x-slot:titleSuffix>

        <x-slot:info>
            <div
                class="grid grid-cols-2 gap-3"
                data-ui="event-show-info"
            >
                <x-stat
                    title="{{ __('ui.events.confirmed_participants') }}"
                    value="{{ $confirmedParticipantsCount }}/{{ $availablePlacesLabel }}"
                    icon="o-users"
                    class="ui-stat-embed ui-activity-show-stat"
                />
                <x-stat
                    title="{{ __('ui.events.confirmed_activities') }}"
                    value="{{ $confirmedActivitiesCount }}"
                    icon="o-puzzle-piece"
                    class="ui-stat-embed ui-activity-show-stat"
                />
            </div>
        </x-slot:info>
    </x-page-header>

    <div
        id="ui-event-show-hero"
        class="ui-event-show-hero ui-content-card relative min-h-[min(32rem,70dvh)] rounded-2xl mb-4 md:mb-6"
    >
        <x-ui.tabs-with-toolbar
            wire:model.live.preserve-scroll="tab"
            label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
            label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
            active-class="!text-base-content border-b border-primary text-primary"
            tabs-class="w-full"
            toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
            data-ui="event-show-tabs"
        >
            <x-slot:toolbar>
                <div class="flex shrink-0 items-center gap-1" data-ui="event-show-tabs-toolbar">
                    @if ($sharePayload)
                        <x-ui.share-menu :payload="$sharePayload" />
                    @endif
                    @if ($calendarPayload)
                        <x-ui.calendar-menu :payload="$calendarPayload" />
                    @endif
                    @auth
                        @if ($canManageEvent)
                            <x-ui.overflow-menu
                                icon="o-cog-6-tooth"
                                :label="__('ui.common.manage')"
                                panel-class="w-64"
                                data-ui="event-show-manage"
                            >
                                <x-ui.overflow-menu-item
                                    id="ui-event-show-create-slots"
                                    icon="o-plus"
                                    class="ui-action ui-action-create-slots"
                                    wire:click.stop="openSlotCreateModal"
                                    wire:loading.attr="disabled"
                                    wire:target="openSlotCreateModal"
                                    data-ui="event-show-create-slots"
                                >
                                    {{ __('ui.slots.create_slots') }}
                                </x-ui.overflow-menu-item>
                                <x-ui.overflow-menu-item
                                    icon="o-printer"
                                    :href="route('events.participants.pdf', $event)"
                                    external
                                    data-ui="event-show-print-participants"
                                >
                                    {{ __('ui.pdf.roster.print_action') }}
                                </x-ui.overflow-menu-item>
                                <x-ui.overflow-menu-item
                                    icon="o-pencil"
                                    :href="url_with_return(route('events.edit', $event))"
                                    data-ui="event-show-edit-open"
                                >
                                    {{ __('ui.common.edit') }}
                                </x-ui.overflow-menu-item>
                                <x-ui.overflow-menu-item
                                    icon="o-square-2-stack"
                                    :href="route('events.create', ['duplicate' => $event->slug])"
                                    data-ui="event-show-duplicate-open"
                                >
                                    {{ __('ui.events.duplicate_action') }}
                                </x-ui.overflow-menu-item>
                                @if (! $event->isCancelled())
                                    @if (($eventSignupPressureBlocksDelete ?? false))
                                        <x-ui.overflow-menu-item
                                            icon="o-x-circle"
                                            class="text-error"
                                            wire:click="confirmCancelEvent"
                                            data-ui="event-show-cancel-event"
                                        >
                                            {{ __('ui.events.cancel_action') }}
                                        </x-ui.overflow-menu-item>
                                    @else
                                        <x-ui.overflow-menu-item
                                            icon="o-trash"
                                            class="text-error"
                                            wire:click="confirmDeleteEvent"
                                            data-ui="event-show-delete"
                                        >
                                            {{ __('ui.common.delete') }}
                                        </x-ui.overflow-menu-item>
                                    @endif
                                @else
                                    <x-ui.overflow-menu-item
                                        icon="o-arrow-uturn-left"
                                        wire:click="confirmReopenEvent"
                                        data-ui="event-show-reopen-event"
                                    >
                                        {{ __('ui.events.reopen_action') }}
                                    </x-ui.overflow-menu-item>
                                    @if (($eventSignupPressureBlocksDelete ?? false))
                                        <x-ui.overflow-menu-item
                                            icon="o-trash"
                                            class="text-error"
                                            wire:click="confirmDeleteEvent"
                                            data-ui="event-show-delete-after-cancel"
                                        >
                                            {{ __('ui.common.delete') }}
                                        </x-ui.overflow-menu-item>
                                    @endif
                                @endif
                            </x-ui.overflow-menu>
                        @endif
                        @if ($hasInterest)
                            <x-ui.icon-count-badge :count="$interestedPeopleCount" data-ui="event-show-interest-count">
                                <x-button
                                    type="button"
                                    wire:click="removeInterest"
                                    class="btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                    :tooltip="__('ui.interests.remove_from_interests')"
                                    :aria-label="__('ui.interests.remove_from_interests')"
                                    data-ui="event-show-interest-remove"
                                    icon="s-star"
                                />
                            </x-ui.icon-count-badge>
                        @else
                            <x-ui.icon-count-badge :count="$interestedPeopleCount" data-ui="event-show-interest-count">
                                <x-button
                                    type="button"
                                    wire:click="addInterest"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                    :tooltip="__('ui.interests.add_to_interests')"
                                    :aria-label="__('ui.interests.add_to_interests')"
                                    data-ui="event-show-interest-add"
                                    icon="o-star"
                                />
                            </x-ui.icon-count-badge>
                        @endif
                    @else
                        <x-ui.icon-count-badge :count="$interestedPeopleCount" data-ui="event-show-interest-count">
                            <span
                                class="btn btn-ghost btn-square btn-sm pointer-events-none text-base-content/80"
                                aria-hidden="true"
                            >
                                <x-icon name="o-star" class="h-5 w-5" />
                            </span>
                        </x-ui.icon-count-badge>
                    @endauth
                </div>
            </x-slot:toolbar>

            <x-tab name="plan" :label="__('ui.events.show_plan')" class="!p-0" data-ui="event-show-tab-plan" icon="o-calendar-days">
                @if (in_array('plan', $mountedTabs, true))
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

            <x-tab name="map" :label="__('ui.events.show_map')" class="!p-0" data-ui="event-show-tab-map" icon="o-map-pin">
                @if (in_array('map', $mountedTabs, true))
                    <livewire:events.event-show-map-tab
                        defer
                        :event-id="$eventId"
                        :active-tab="$tab"
                        wire:key="event-map-{{ $eventId }}"
                    />
                @endif
            </x-tab>

            @if ($canManageEvent && $hasPendingProposals)
                <x-tab name="proposals" :label="__('ui.events.show_proposals')" class="!p-0" data-ui="event-show-tab-proposals" icon="o-clipboard-document-list">
                    @if (in_array('proposals', $mountedTabs, true))
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
    </div>

    @if ($canManageEvent ?? false)
        @include('slots.partials.create-modal-shell', [
            'event' => $event,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotNameSuggestions' => $slotNameSuggestions ?? [],
        ])
    @endif
    @include('slots.partials.edit-modal-shell')
    @include('livewire.events.partials.activity-preview-modal', [
        'previewActivity' => $previewActivity ?? null,
        'previewAbout' => $previewAbout ?? null,
        'previewActivityBadgeItems' => $previewActivityBadgeItems ?? [],
        'previewActivityParticipation' => $previewActivityParticipation ?? null,
        'previewActivityHasActiveEnrollmentWindow' => $previewActivityHasActiveEnrollmentWindow ?? false,
        'showPreviewParticipationActions' => $showPreviewParticipationActions ?? false,
        'showPreviewParticipationTab' => $showPreviewParticipationTab ?? false,
        'activityPreviewRefreshTick' => $activityPreviewRefreshTick ?? 0,
    ])

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
