<div
    id="ui-event-show-slots"
    class="ui-event-show-slots p-0 sm:p-6"
    data-ui="event-show-slots"
    x-data="{
        selectedProposalSlotIds: $wire.entangle('proposalSlotIds'),
        proposeActivityBaseUrl: @js(url_with_return(route('activities.create'), route('events.show', ['event' => $event, 'tab' => 'plan'], false))),
        proposeReturnPath: @js(route('events.show', ['event' => $event, 'tab' => 'plan'], false)),
        proposalEventId: {{ (int) $event->id }},
        toggleProposalSlot(slotId) {
            const normalizedIds = this.selectedProposalSlotIds.map((id) => Number(id));

            if (normalizedIds.includes(slotId)) {
                this.selectedProposalSlotIds = normalizedIds.filter((id) => id !== slotId);
            } else {
                this.selectedProposalSlotIds = [...normalizedIds, slotId];
            }
        },
        proposeActivityHref() {
            const params = new URLSearchParams();
            params.set('proposal_event_id', String(this.proposalEventId));
            params.set('return', this.proposeReturnPath);
            for (const id of this.selectedProposalSlotIds.map((id) => Number(id))) {
                params.append('proposal_slot_ids[]', String(id));
            }

            const base = this.proposeActivityBaseUrl.split('?')[0];
            const existing = new URLSearchParams(this.proposeActivityBaseUrl.includes('?') ? this.proposeActivityBaseUrl.split('?')[1] : '');
            for (const [key, value] of params.entries()) {
                existing.set(key, value);
            }

            return base + '?' + existing.toString();
        }
    }"
>
    @php
        $hasEventDescription = filled(rich_text_excerpt($event->description));
        $hasEnrollmentWindows = $event->enrollmentWindows->isNotEmpty();
        $hasEntityLinks = $event->links->isNotEmpty() || ($canManageEvent ?? false);
        $autoOpenDone = false;
        $proposeReturnPath = route('events.show', ['event' => $event, 'tab' => 'plan'], false);
        $guestProposeLoginUrl = login_url($proposeReturnPath);
    @endphp

    @if ($hasEmptySlots || ($canShowPlanActivityProposalUi ?? false))
        <div class="flex items-center justify-end gap-2 mb-4">
            @if ($canShowPlanActivityProposalUi ?? false)
                <x-button
                    type="button"
                    class="btn-outline btn-sm btn-primary"
                    x-on:click="document.getElementById('ui-event-show-plan-propose-hero')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    data-ui="event-show-scroll-to-propose-hero"
                >
                    {{ __('ui.events.want_to_propose_activity') }}
                </x-button>
            @endif
            @if ($hasEmptySlots && false)
                <x-button
                    type="button"
                    wire:click="toggleShowEmptySlots"
                    wire:loading.attr="disabled"
                    wire:target="toggleShowEmptySlots"
                    class="btn-outline btn-sm btn-neutral"
                    :aria-label="$showEmptySlots ? __('ui.events.hide_empty_slots') : __('ui.events.show_empty_slots')"
                    data-ui="event-show-toggle-empty-slots"
                >
                    <span wire:loading.remove wire:target="toggleShowEmptySlots">
                        {{ $showEmptySlots ? __('ui.events.hide_empty_slots') : __('ui.events.show_empty_slots') }}
                    </span>
                    <span wire:loading wire:target="toggleShowEmptySlots" class="inline-flex items-center gap-2">
                        <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                        {{ __('ui.common.loading') }}
                    </span>
                </x-button>
            @endif
        </div>
    @endif

    @if ($hasEventDescription || $hasEnrollmentWindows || $hasEntityLinks)
        <div class="py-2" data-ui="event-show-plan-meta">
            <x-collapse
                class="border border-primary/25 bg-base-200/40"
                separator
                :open="! $activeEnrollmentWindow"
                data-ui="event-show-plan-info"
            >
                <x-slot:heading>
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <span class="text-base font-semibold text-base-content">{{ __('ui.events.show_info') }}</span>
                        @if ($activeEnrollmentWindow)
                            <span class="badge badge-success badge-sm shrink-0" data-ui="event-show-plan-info-enrollment-badge">
                                {{ __('ui.events.enrollment_window_active_badge') }}
                            </span>
                        @endif
                    </span>
                </x-slot:heading>
                <x-slot:content>
                    @if ($hasEventDescription)
                        <div class="rich-text-content text-base-content/80 p-1" data-ui="event-show-plan-info-description">
                            {!! rich_text($event->description) !!}
                        </div>
                    @endif

                    @if ($hasEntityLinks)
                        <div
                            @class(['mt-2' => $hasEventDescription])
                            data-ui="event-show-plan-links"
                        >
                            <livewire:entity-links.manage-entity-links
                                :linkable="$event"
                                :show-list="true"
                                :listen-for-open-add="false"
                                instance-suffix="plan"
                                appearance="default"
                                data-ui="event-show-entity-links"
                                :key="'event-entity-links-plan-'.$event->id"
                            />
                        </div>
                    @endif

                    @if ($hasEnrollmentWindows)
                        <div @class(['mt-2' => $hasEventDescription || $hasEntityLinks]) data-ui="event-show-plan-enrollment">
                            @include('livewire.events.partials.show-plan-enrollment-windows', [
                                'event' => $event,
                                'activeEnrollmentWindow' => $activeEnrollmentWindow,
                            ])
                        </div>
                    @endif
                </x-slot:content>
            </x-collapse>
        </div>
    @endif

    @auth
        @if ($canShowPlanActivityProposalUi ?? false)
            @php
                $proposeActivityUrl = ! empty($proposalSlotIds)
                    ? url_with_return(
                        route('activities.create').'?'.http_build_query([
                            'proposal_event_id' => $event->id,
                            'proposal_slot_ids' => array_map('intval', $proposalSlotIds),
                        ]),
                        $proposeReturnPath,
                    )
                    : url_with_return(route('activities.create', ['proposal_event_id' => $event->id]), $proposeReturnPath);
            @endphp
        @endif
    @endauth
    <ul class="space-y-6">
        @forelse ($slotHourGroups as $group)
            @php
                $visibleSlots = $showEmptySlots
                    ? $group['slots']
                    : $group['slots']->filter(fn ($slot) => $slot->activity !== null)->values();
            @endphp
            <li class="list-none">
                @if ($visibleSlots->isNotEmpty())
                    @php
                        $groupStartsAt = $group['starts_at'] ?? null;
                        $shouldAutoOpen = ! $autoOpenDone;
                        if ($shouldAutoOpen) {
                            $autoOpenDone = true;
                        }
                    @endphp
                    <x-collapse
                        class="ui-timeline-collapse"
                        :data-ui="$groupStartsAt ? 'event-slot-group-'.$groupStartsAt->getTimestamp() : null"
                        separator
                        :open="$shouldAutoOpen"
                    >
                        <x-slot:heading>
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                                {{ $group['label'] }}
                            </p>
                        </x-slot:heading>
                        <x-slot:content>
                            <ul class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($visibleSlots as $slot)
                            @php
                                $activity = $slot->activity;
                                $participantsCount = $activity
                                    ? (int) ($activity->participants_count ?? 0)
                                    : (filled($slot->max_capacity) ? $slot->max_capacity : null);
                                $slotBadgeItems = $activity
                                    ? ($slotCardBadgeItemsByActivityId[(int) $activity->id] ?? [])
                                    : [];
                                $slotTypeBadgeItems = ! $activity
                                    ? ($slotTypeBadgeItemsBySlotId[(int) $slot->id] ?? [])
                                    : [];
                                $showActivityDetailsLink = $activity?->isPubliclyShowable() ?? false;
                            @endphp
                            <li
                                @class([
                                    'ui-tile-active' => $activity && !$activity?->isCancelled(),
                                    'ui-tile-empty' => ! $activity || $activity?->isCancelled(),
                                    'status-dots group relative w-full overflow-visible rounded-xl border border-transparent',
                                    'status-dots-active ui-tile-pressable !border-primary/80 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-lg hover:shadow-primary/15 motion-reduce:hover:translate-y-0' => $activity,
                                    'transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/45 hover:bg-primary/5 hover:shadow-md hover:shadow-primary/10 motion-reduce:hover:translate-y-0' => ! $activity,
                                    'cursor-pointer select-none' => $activity || (auth()->check() && ! $activity && ($canShowPlanActivityProposalUi ?? false)),
                                ])
                                @if (auth()->check() && ! $activity && ($canShowPlanActivityProposalUi ?? false))
                                    x-on:click="toggleProposalSlot({{ $slot->id }})"
                                    :class="selectedProposalSlotIds.includes({{ (int) $slot->id }}) ? 'ui-tile-marked' : ''"
                                @endif
                            >
                                @if ($activity)
                                    @php
                                        $activityCoverPicture = $activityCoverPicturesById[(int) $activity->id] ?? null;
                                    @endphp
                                    @if ($activityCoverPicture?->hasDisplayableImage())
                                        <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden rounded-xl" aria-hidden="true">
                                            <div class="absolute inset-0 scale-105">
                                                <x-listing-card-picture
                                                    :picture="$activityCoverPicture"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                            <div class="absolute inset-0 bg-base-100/85"></div>
                                            <div class="absolute inset-0 bg-gradient-to-t from-base-100/80 via-base-100/40 to-base-100/25"></div>
                                        </div>
                                    @endif
                                @endif
                                <div class="status-dots-toolbar relative z-[3] flex items-center">
                                    <div class="flex-1"></div>
                                    @auth
                                        @php
                                            $showDetachActivity = $canManageEvent && $activity;
                                            $showSlotEditDelete = auth()->user()?->canModifyEntity($slot) ?? false;
                                        @endphp
                                        @if ($showDetachActivity || $showSlotEditDelete || $activity)
                                            <div class="flex items-center justify-end relative z-[3] gap-1 pointer-events-auto" @if (! $activity) onclick="event.stopPropagation()" @endif>
                                                @if ($showSlotEditDelete)
                                                    <x-ui.overflow-menu
                                                        icon="o-cog-6-tooth"
                                                        :label="__('ui.common.manage')"
                                                        panel-class="w-64"
                                                        data-ui="event-show-slot-manage-{{ $slot->id }}"
                                                    >
                                                        <x-ui.overflow-menu-item
                                                            icon="o-pencil"
                                                            data-ui="event-show-slot-edit"
                                                            x-on:click="window.openSlotEditModal?.({{ $slot->id }})"
                                                        >
                                                            {{ __('ui.events.edit_slot') }}
                                                        </x-ui.overflow-menu-item>
                                                        @if ($showDetachActivity)
                                                            <x-ui.overflow-menu-item
                                                                icon="o-link-slash"
                                                                class="text-warning"
                                                                wire:click="confirmDetachActivityFromSlot({{ $slot->id }})"
                                                                data-ui="event-show-slot-detach"
                                                            >
                                                                {{ __('ui.events.detach_activity_from_slot') }}
                                                            </x-ui.overflow-menu-item>
                                                        @endif
                                                        @if ($canManageEvent && $activity)
                                                            @if ($activity->isCancelled())
                                                                <x-ui.overflow-menu-item
                                                                    icon="o-arrow-uturn-left"
                                                                    wire:click="confirmReopenSlotActivity({{ $slot->id }})"
                                                                    data-ui="event-show-slot-reopen-activity"
                                                                >
                                                                    {{ __('ui.activities.reopen_action') }}
                                                                </x-ui.overflow-menu-item>
                                                            @else
                                                                <x-ui.overflow-menu-item
                                                                    icon="o-x-circle"
                                                                    class="text-error"
                                                                    wire:click="confirmCancelSlotActivity({{ $slot->id }})"
                                                                    data-ui="event-show-slot-cancel-activity"
                                                                >
                                                                    {{ __('ui.activities.cancel_action') }}
                                                                </x-ui.overflow-menu-item>
                                                            @endif
                                                        @endif
                                                        <x-ui.overflow-menu-item
                                                            icon="o-trash"
                                                            class="text-error"
                                                            wire:click="confirmDeleteSlot({{ $slot->id }})"
                                                            data-ui="event-show-slot-delete"
                                                        >
                                                            {{ __('ui.common.delete') }}
                                                        </x-ui.overflow-menu-item>
                                                    </x-ui.overflow-menu>
                                                @endif
                                                @if ($activity)
                                                    @if ($showActivityDetailsLink)
                                                        <x-button
                                                            :link="route('activities.show', $activity)"
                                                            wire:navigate
                                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                            :aria-label="__('ui.activities.show_details').': '.$activity->name"
                                                            :tooltip="__('ui.activities.show_details')"
                                                            icon="o-arrow-top-right-on-square"
                                                            data-ui="event-show-slot-open-details"
                                                        />
                                                    @endif
                                                    @php
                                                        $isInterestedInActivity = in_array((int) $activity->id, $interestedActivityIds ?? [], true);
                                                    @endphp
                                                    @if ($isInterestedInActivity)
                                                        <x-button
                                                            type="button"
                                                            wire:click="removeActivityInterest({{ (int) $activity->id }})"
                                                            class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                                            :tooltip="__('ui.interests.remove_from_interests')"
                                                            data-ui="event-show-slot-interest-remove"
                                                            icon="s-star"
                                                        />
                                                    @else
                                                        <x-button
                                                            type="button"
                                                            wire:click="addActivityInterest({{ (int) $activity->id }})"
                                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                                            :tooltip="__('ui.interests.add_to_interests')"
                                                            data-ui="event-show-slot-interest-add"
                                                            icon="o-star"
                                                        />
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        @if ($showActivityDetailsLink)
                                            <div class="flex justify-end relative z-[3] pointer-events-auto">
                                                <x-button
                                                    :link="route('activities.show', $activity)"
                                                    wire:navigate
                                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                    :aria-label="__('ui.activities.show_details').': '.$activity->name"
                                                    :tooltip="__('ui.activities.show_details')"
                                                    icon="o-arrow-top-right-on-square"
                                                    data-ui="event-show-slot-open-details"
                                                />
                                            </div>
                                        @endif
                                    @endauth
                                </div>
                                <div class="px-3 pb-3 sm:px-4">
                                    @if ($activity)
                                        <button
                                            type="button"
                                            wire:click="openActivityPreview({{ (int) $activity->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openActivityPreview({{ (int) $activity->id }})"
                                            wire:loading.class.delay="cursor-wait"
                                            class="absolute inset-0 z-[1] block cursor-pointer rounded-lg bg-primary/[0.02] ring-inset ring-primary/0 transition duration-200 group-hover:ring-2 group-hover:ring-primary/25 active:bg-primary/[0.08] active:ring-2 active:ring-primary/40 motion-reduce:transition-none"
                                            aria-label="{{ $activity->name }}"
                                            data-ui="event-show-slot-open-activity-preview"
                                        >
                                        </button>
                                        <div
                                            wire:loading.delay
                                            wire:target="openActivityPreview({{ (int) $activity->id }})"
                                            class="pointer-events-auto absolute inset-0 z-[15] flex items-center justify-center rounded-xl bg-base-100/60 backdrop-blur-[1px]"
                                            aria-live="polite"
                                            role="status"
                                            data-ui="event-show-slot-activity-preview-loading"
                                        >
                                            <span class="sr-only">{{ __('ui.common.loading') }}</span>
                                            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
                                        </div>
                                    @endif
                                    <div @class(['relative z-[2] flex items-start justify-between gap-2', 'pointer-events-none' => $activity])>
                                        <div class="min-w-0 flex-1 space-y-1.5">
                                            @if ($activity)
                                                <div class="flex items-baseline gap-2">
                                                    <h4 class="min-w-0 flex-1 truncate text-base font-semibold leading-snug text-base-content">{{ $activity->name }}</h4>
                                                    <span class="inline-flex shrink-0 items-center gap-1.5 text-sm tabular-nums text-base-content/75" title="{{ (int) ($activity->participants_count ?? 0) }}/{{ $activity->max_participants ?? '∞' }}" aria-label="{{ (int) ($activity->participants_count ?? 0) }}/{{ $activity->max_participants ?? '∞' }}">
                                                        <x-icon name="o-users" class="h-4 w-4 shrink-0 text-base-content/50" />
                                                        <span>{{ (int) ($activity->participants_count ?? 0) }}/{{ $activity->max_participants ?? '∞' }}</span>
                                                    </span>
                                                </div>
                                                @if ($activity->isCancelled())
                                                    <div class="mt-1">
                                                        <span class="badge badge-warning">{{ __('ui.activities.cancelled_badge') }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                            @php
                                                $slotPlaceLabel = $slot->place?->planLabel($eventHasMultipleVenues ?? false);
                                            @endphp
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                                <span @class(['font-medium text-base-content' => ! $activity, 'font-medium text-base-content/85' => $activity])>{{ $slot->name }}</span>
                                                @if ($slot->starts_at || $slot->ends_at)
                                                    <span class="inline-flex items-center gap-1.5 tabular-nums text-base-content/75">
                                                        <x-icon name="o-clock" class="h-4 w-4 shrink-0 text-base-content/50" />
                                                        <span>
                                                            @if ($slot->starts_at && $slot->ends_at)
                                                                {{ format_in_user_tz($slot->starts_at, 'H:i') }}<span class="text-base-content/45"> – </span>{{ format_in_user_tz($slot->ends_at, 'H:i') }}
                                                            @elseif ($slot->starts_at)
                                                                {{ format_in_user_tz($slot->starts_at, 'H:i') }}
                                                            @else
                                                                {{ format_in_user_tz($slot->ends_at, 'H:i') }}
                                                            @endif
                                                        </span>
                                                    </span>
                                                @endif
                                                @if ($slotPlaceLabel)
                                                    <span class="inline-flex min-w-0 items-center gap-1.5 text-base-content/75" data-ui="event-show-slot-place">
                                                        <x-icon name="o-map-pin" class="h-4 w-4 shrink-0 text-base-content/50" />
                                                        <span class="min-w-0 truncate">{{ $slotPlaceLabel }}</span>
                                                    </span>
                                                @endif
                                                @if (! $activity && $participantsCount !== null)
                                                    <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ $participantsCount }}" aria-label="{{ $participantsCount }}">
                                                        <x-icon name="o-users" class="h-4 w-4 shrink-0" />
                                                        <span>{{ $participantsCount }}</span>
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($activity && isset($activeWindowRemainingByActivityId[(int) $activity->id]))
                                                <p class="text-xs text-base-content/70">
                                                    {{ __('ui.events.enrollment_window_activity_spots_remaining', [
                                                        'remaining' => $activeWindowRemainingByActivityId[(int) $activity->id],
                                                        'max' => $activeEnrollmentWindow?->maxAllowedParticipantsPerActivityEffective(),
                                                    ]) }}
                                                </p>
                                            @endif
                                            @if ($activity)
                                                @if ($activity->isCancelled() && $activity->cancel_reason)
                                                    <p class="mt-2 text-xs text-error">{{ __('ui.activities.cancel_reason_label') }}: {{ $activity->cancel_reason }}</p>
                                                @endif
                                                @if ($activity->creator)
                                                    <div class="relative z-[3] mt-1 inline-flex w-fit max-w-full pointer-events-auto" data-ui="event-show-slot-host">
                                                        <x-user-badge
                                                            :user="$activity->creator"
                                                            size="sm"
                                                            :context-activity-id="$activity->id"
                                                            name-class="truncate text-xs font-medium text-base-content"
                                                        />
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    @if ($activity)
                                        <div @class(['relative z-[2] mt-4', 'pointer-events-none' => $activity])>
                                            <x-ui.activity-badge-group
                                                :items="$slotBadgeItems"
                                                data-ui="event-show-slot-badge-group"
                                            />
                                        </div>
                                    @elseif (! empty($slotTypeBadgeItems))
                                        <div class="relative z-[2] mt-4">
                                            <x-ui.activity-badge-group
                                                :items="$slotTypeBadgeItems"
                                                data-ui="event-show-slot-type-badges"
                                            />
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                        </x-slot:content>
                    </x-collapse>
                @elseif (! empty($group['boundary']))
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/55 px-4">
                        {{ $group['label'] }}
                    </p>
                    <ul class="grid grid-cols-1 gap-3">
                        <li class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/70">
                                @if ($group['boundary'] === 'event_start')
                                    <x-ui.hr
                                        color="neutral"
                                        text="{{ __('ui.events.event_boundary_starts') }}"
                                        left-edge-icon="s-chevron-right"
                                        right-edge-icon="s-chevron-left"
                                        left-edge-icon-class="absolute left-1/2 -translate-x-18 w-4 h-4"
                                        right-edge-icon-class="absolute left-1/2 translate-x-14 w-4 h-4"
                                    />
                                @else
                                    <x-ui.hr
                                        color="neutral"
                                        text="{{ __('ui.events.event_boundary_ends') }}"
                                        left-edge-icon="s-chevron-right"
                                        right-edge-icon="s-chevron-left"
                                        left-edge-icon-class="absolute left-1/2 -translate-x-18 w-4 h-4"
                                        right-edge-icon-class="absolute left-1/2 translate-x-14 w-4 h-4"
                                    />
                                @endif
                            </p>
                        </li>
                    </ul>
                @endif
            </li>
        @empty
            <li class="py-2 text-sm text-base-content/70">{{ __('ui.events.no_slots_yet') }}</li>
        @endforelse
    </ul>
    @if ($canShowPlanActivityProposalUi ?? false)
        @auth
            @php
                $proposeActivityUrl = ! empty($proposalSlotIds)
                    ? url_with_return(
                        route('activities.create').'?'.http_build_query([
                            'proposal_event_id' => $event->id,
                            'proposal_slot_ids' => array_map('intval', $proposalSlotIds),
                        ]),
                        $proposeReturnPath,
                    )
                    : url_with_return(route('activities.create', ['proposal_event_id' => $event->id]), $proposeReturnPath);
            @endphp
        @endauth
        <div class="mt-8 flex w-full justify-center" data-ui="event-show-plan-propose-footer">
            <div id="ui-event-show-plan-propose-hero" class="mb-6 flex w-full justify-center pb-4" data-ui="event-show-plan-propose-hero">
                <div class="hero w-full max-w-2xl rounded-2xl box-glow-neutral">
                    <div class="hero-content flex-col px-5 py-8 text-center sm:px-10">
                        <div class="max-w-xl px-2">
                            <h2 class="text-2xl font-bold leading-tight tracking-tight text-base-content sm:text-3xl">
                                {{ __('ui.events.plan_propose_hero_title') }}
                            </h2>
                            <p class="py-5 text-base leading-relaxed text-base-content/80">
                                {{ __('ui.events.plan_propose_hero_description') }}
                            </p>
                            @auth
                                <x-ui.magic-button
                                    id="ui-event-show-propose-primary"
                                    :link="$proposeActivityUrl"
                                    class="ui-action ui-action-propose"
                                    data-ui="event-show-propose"
                                    x-bind:href="proposeActivityHref()"
                                    wire:navigate
                                >
                                    {{ __('ui.events.propose_activity') }}
                                </x-ui.magic-button>
                            @else
                                <x-ui.magic-button
                                    id="ui-event-show-propose-primary"
                                    :link="$guestProposeLoginUrl"
                                    :no-wire-navigate="true"
                                    class="ui-action ui-action-propose"
                                    data-ui="event-show-propose-guest"
                                >
                                    {{ __('ui.events.propose_activity') }}
                                </x-ui.magic-button>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
