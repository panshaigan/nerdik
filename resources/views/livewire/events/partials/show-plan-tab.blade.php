@php use App\Enums\BadgeSemantic; @endphp
<div
    id="ui-event-show-slots"
    class="ui-event-show-slots p-4 sm:p-6"
    data-ui="event-show-slots"
    x-data="{
        selectedProposalSlotIds: $wire.entangle('proposalSlotIds'),
        proposeActivityBaseUrl: @js(route('activities.create')),
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
            for (const id of this.selectedProposalSlotIds.map((id) => Number(id))) {
                params.append('proposal_slot_ids[]', String(id));
            }

            return this.proposeActivityBaseUrl + '?' + params.toString();
        }
    }"
>
    @php
        $now = now();
        $autoOpenDone = false;
    @endphp
    @auth
        @if ($canShowPlanActivityProposalUi ?? false)
            @php
                $proposeActivityUrl = ! empty($proposalSlotIds)
                    ? route('activities.create').'?'.http_build_query([
                        'proposal_event_id' => $event->id,
                        'proposal_slot_ids' => array_map('intval', $proposalSlotIds),
                    ])
                    : route('activities.create', ['proposal_event_id' => $event->id]);
            @endphp
        @endif
    @endauth
    <div class="mb-4 flex items-center justify-end gap-2">
        @auth
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
        @endauth
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
    </div>
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
                        $groupHasAttachedActivities = $groupStartsAt !== null
                            && ($group['slots'] ?? collect())->contains(fn ($slot) => $slot->activity !== null);
                        $shouldAutoOpen = ! $autoOpenDone
                            && $groupStartsAt !== null
                            && $groupHasAttachedActivities
                            && $groupStartsAt->gte($now);
                        if ($shouldAutoOpen) {
                            $autoOpenDone = true;
                        }
                    @endphp
                    <x-collapse
                        :data-ui="$groupStartsAt ? 'event-slot-group-'.$groupStartsAt->getTimestamp() : null"
                        separator
                        :open="$shouldAutoOpen"
                    >
                        <x-slot:heading>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/55">
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
                                $slotBadgeItems = [];
                                $slotTypeBadgeItems = [];
                                if ($activity) {
                                    $slotBadgeItems = app(\App\Domain\ActivityBadges\ActivityBadgeGroupBuilder::class)->build(
                                        $activity,
                                        \App\Domain\ActivityBadges\ActivityBadgeGroupConfig::eventSlotCard(),
                                    );
                                } else {
                                    $slotActivityTypes = collect($slot->activityTypes)
                                        ->map(fn ($row) => $row->slug ? __('ui.activities.types.'.$row->slug) : null)
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    if ($slotActivityTypes->isNotEmpty()) {
                                        $slotTypeBadgeItems = app(\App\Domain\ActivityBadges\ActivityBadgeGroupBuilder::class)
                                            ->buildActivityTypeChips($slotActivityTypes, BadgeSemantic::Info);
                                    }
                                }
                            @endphp
                            <li
                                @class([
                                    'slot-browser-card group relative w-full overflow-hidden rounded-xl border border-transparent',
                                    'ui-glow-card' => $activity,
                                    'activity-attached !border-primary/80 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-lg hover:shadow-primary/15 motion-reduce:hover:translate-y-0' => $activity,
                                    'transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/45 hover:bg-primary/5 hover:shadow-md hover:shadow-primary/10 motion-reduce:hover:translate-y-0' => ! $activity,
                                    'cursor-pointer' => auth()->check() && ! $activity && ($canShowPlanActivityProposalUi ?? false),
                                    'ui-glow-card-empty' => ! $activity,
                                    'ui-glow-card-alert' => $activity?->isCancelled(),
                                ])
                                @if (auth()->check() && ! $activity && ($canShowPlanActivityProposalUi ?? false))
                                    x-on:click="toggleProposalSlot({{ $slot->id }})"
                                    :class="selectedProposalSlotIds.includes({{ (int) $slot->id }}) ? 'ui-glow-card-marked' : ''"
                                @endif
                            >
                                <div class="slot-browser-card-toolbar flex items-center">
                                    <div class="flex-1"></div>
                                    @auth
                                        @php
                                            $showDetachActivity = $canManageEvent && $activity;
                                            $showSlotEditDelete = auth()->user()?->canModifyEntity($slot) ?? false;
                                        @endphp
                                        @if ($showDetachActivity || $showSlotEditDelete || $activity)
                                            <div class="flex justify-end relative z-[3] gap-0.5 pointer-events-auto" @if (! $activity) onclick="event.stopPropagation()" @endif>
                                                @if ($showSlotEditDelete)
                                                    <x-button
                                                        type="button"
                                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                                        :tooltip="__('ui.events.edit_slot')"
                                                        :aria-label="__('ui.events.edit_slot')"
                                                        onclick="window.openSlotEditModal?.({{ $slot->id }})"
                                                        icon="o-pencil"
                                                    />
                                                    @if ($showDetachActivity)
                                                        <x-button
                                                            type="button"
                                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                                            :tooltip="__('ui.events.detach_activity_from_slot')"
                                                            :aria-label="__('ui.events.detach_activity_from_slot')"
                                                            wire:click="confirmDetachActivityFromSlot({{ $slot->id }})"
                                                            icon="o-link-slash"
                                                        />
                                                    @endif
                                                    @if ($canManageEvent && $activity)
                                                        @if ($activity->isCancelled())
                                                            <x-button
                                                                type="button"
                                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
                                                                :tooltip="__('ui.activities.reopen_action')"
                                                                :aria-label="__('ui.activities.reopen_action')"
                                                                wire:click="confirmReopenSlotActivity({{ $slot->id }})"
                                                                icon="o-arrow-uturn-left"
                                                            />
                                                        @else
                                                            <x-button
                                                                type="button"
                                                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                                                :tooltip="__('ui.activities.cancel_action')"
                                                                :aria-label="__('ui.activities.cancel_action')"
                                                                wire:click="confirmCancelSlotActivity({{ $slot->id }})"
                                                                icon="o-x-circle"
                                                            />
                                                        @endif
                                                    @endif
                                                    <x-button
                                                        type="button"
                                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                                        :tooltip="__('Delete')"
                                                        :aria-label="__('Delete')"
                                                        wire:click="confirmDeleteSlot({{ $slot->id }})"
                                                        icon="o-trash"
                                                    />
                                                    @if ($activity)
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
                                                @endif
                                            </div>
                                        @endif
                                    @endauth
                                </div>
                                <div class="px-4 pb-6">
                                    @if ($activity)
                                        <button
                                            type="button"
                                            wire:click="openActivityPreview({{ (int) $activity->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openActivityPreview({{ (int) $activity->id }})"
                                            wire:loading.class.delay="cursor-wait"
                                            class="absolute inset-0 z-[1] block cursor-pointer rounded-lg bg-primary/[0.02] ring-inset ring-primary/0 transition duration-200 group-hover:ring-2 group-hover:ring-primary/25 motion-reduce:transition-none"
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
                                                <h4 class="text-base font-semibold leading-snug text-base-content">{{ $activity->name }}</h4>
                                                @if ($activity->isCancelled())
                                                    <div class="mt-1">
                                                        <span class="badge badge-warning">{{ __('ui.activities.cancelled_badge') }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                                <span @class(['font-medium text-base-content' => ! $activity, 'font-medium text-base-content/85' => $activity])>{{ $slot->name }}</span>
                                                @if ($slot->starts_at || $slot->ends_at)
                                                    <span class="inline-flex items-center gap-1.5 tabular-nums text-base-content/75">
                                                        <svg class="h-4 w-4 shrink-0 text-base-content/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                        </svg>
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
                                                @if ($activity)
                                                    <span class="badge badge-primary badge-sm">
                                                        {{ (int) ($activity->participants_count ?? 0) }}/{{ $activity->max_participants ?? '∞' }}
                                                    </span>
                                                @elseif ($participantsCount !== null)
                                                    <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ $participantsCount }}" aria-label="{{ $participantsCount }}">
                                                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                                        </svg>
                                                        <span>{{ $participantsCount }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                                                @if ($slot->place)
                                                    <span class="inline-flex shrink-0 items-center gap-1.5 text-base-content/60">
                                                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                        </svg>
                                                        <span>{{ $slot->place->venueRoomLabel() }}</span>
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
                                            @endif
                                        </div>
                                    </div>
                                    @if ($activity)
                                        <div @class(['relative z-[2] mt-2', 'pointer-events-none' => $activity])>
                                            <x-ui.activity-badge-group
                                                :items="$slotBadgeItems"
                                                data-ui="event-show-slot-badge-group"
                                            />
                                        </div>
                                    @elseif (! empty($slotTypeBadgeItems))
                                        <div class="relative z-[2] mt-2">
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
                    <ul class="grid grid-cols-1 gap-3">
                        <li class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/70">
                                @if ($group['boundary'] === 'event_start')
                                    <x-ui.hr
                                        text="{{ __('ui.events.event_boundary_starts') }}"
                                        left-edge-icon="s-star"
                                        right-edge-icon="s-star"
                                        left-edge-icon-class="absolute left-1/2 -translate-x-18 w-4 h-4 text-primary/80"
                                        right-edge-icon-class="absolute left-1/2 translate-x-14 w-4 h-4 text-primary/80"
                                    />
                                @else
                                    <x-ui.hr
                                        text="{{ __('ui.events.event_boundary_ends') }}"
                                        left-edge-icon="s-star"
                                        right-edge-icon="s-star"
                                        left-edge-icon-class="absolute left-1/2 -translate-x-18 w-4 h-4 text-primary/80"
                                        right-edge-icon-class="absolute left-1/2 translate-x-14 w-4 h-4 text-primary/80"
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
    @auth
        @if ($canShowPlanActivityProposalUi ?? false)
            @php
                $proposeActivityUrl = ! empty($proposalSlotIds)
                    ? route('activities.create').'?'.http_build_query([
                        'proposal_event_id' => $event->id,
                        'proposal_slot_ids' => array_map('intval', $proposalSlotIds),
                    ])
                    : route('activities.create', ['proposal_event_id' => $event->id]);
            @endphp
            <div class="mt-8 flex w-full justify-center" data-ui="event-show-plan-propose-footer">
                <div id="ui-event-show-plan-propose-hero" class="mb-6 flex w-full justify-center pb-4" data-ui="event-show-plan-propose-hero">
                    <div class="hero bg-base-200 w-full max-w-2xl rounded-2xl line-glow-primary">
                        <div class="hero-content flex-col px-5 py-8 text-center sm:px-10">
                            <div class="max-w-xl px-2">
                                <h2 class="text-2xl font-bold leading-tight tracking-tight text-base-content sm:text-3xl">
                                    {{ __('ui.events.plan_propose_hero_title') }}
                                </h2>
                                <p class="py-5 text-base leading-relaxed text-base-content/80">
                                    {{ __('ui.events.plan_propose_hero_description') }}
                                </p>
                                <x-button
                                    id="ui-event-show-propose-primary"
                                    :link="$proposeActivityUrl"
                                    class="btn-primary btn-md ui-action ui-action-propose ui-action-propose-magic"
                                    data-ui="event-show-propose"
                                    x-bind:href="proposeActivityHref()"
                                    wire:navigate
                                >
                                    {{ __('ui.events.propose_activity') }}
                                </x-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endauth
</div>
