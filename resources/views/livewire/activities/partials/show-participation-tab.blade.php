@php
    $activityStartsAtGmt = $activity->hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED
        ? $activity->starts_at
        : $activity->slot?->starts_at;
    $canMarkAbsentNow = $activityStartsAtGmt !== null
        && $activityStartsAtGmt->clone()->utc()->lte(now('UTC'));
@endphp

<div data-ui="activity-show-participation">
    <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="activity-show-participation-columns">
        <div class="min-w-0" data-ui="activity-show-participants">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
            @forelse ($activity->participants as $p)
                <x-list-item :item="$p" :avatar="false" value="id" sub-value="id" class="px-3 py-3 ">
                    <x-slot:value class="truncate text-sm font-medium text-base-content">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-user-badge
                                    :user="$p->user"
                                    size="sm"
                                    :subline="((int) $p->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                                    name-class="truncate text-sm font-medium text-base-content"
                                    class="min-w-0 flex-1"
                                />
                                @if ($p->is_absent)
                                    <span class="badge badge-warning badge-sm shrink-0">{{ __('ui.activities.absent') }}</span>
                                @endif
                            </div>
                    </x-slot:value>
                    <x-slot:sub-value class="truncate text-xs text-base-content/65">
                        @if ((int) $p->user_id === (int) ($activity->created_by ?? 0))
                            {{ __('ui.activities.host') }}
                        @endif
                    </x-slot:sub-value>
                    @if ($canManageActivity && (int) $p->user_id !== (int) ($activity->created_by ?? 0))
                        <x-slot:actions class="flex items-center gap-1">
                            @if ($p->is_absent)
                                <x-button
                                    type="button"
                                    class="btn btn-sm text-success"
                                    :title="__('ui.activities.unmark_absent')"
                                    :aria-label="__('ui.activities.unmark_absent')"
                                    wire:click="unmarkParticipantAbsent({{ $p->id }})"
                                >{{ __('ui.activities.unmark_absent') }}</x-button>
                            @else
                                @if ($canMarkAbsentNow)
                                    <x-button
                                        type="button"
                                        class="btn btn-sm text-warning"
                                        :title="__('ui.activities.mark_absent')"
                                        :aria-label="__('ui.activities.mark_absent')"
                                        wire:click="markParticipantAbsent({{ $p->id }})"
                                    >{{ __('ui.activities.mark_absent') }}</x-button>
                                @endif
                            @endif
                            <x-button
                                type="button"
                                class="btn-ghost btn-square btn-xs text-error"
                                :title="__('ui.activities.remove_participant')"
                                :aria-label="__('ui.activities.remove_participant')"
                                wire:click="removeParticipant({{ $p->id }})"
                                icon="o-trash"
                            />
                            <x-button
                                type="button"
                                class="btn-warning btn-square btn-xs"
                                icon="o-arrow-right"
                                :title="__('ui.activities.move_to_waitlist')"
                                :aria-label="__('ui.activities.move_to_waitlist')"
                                wire:click="confirmMoveParticipantToWaitlist({{ $p->id }})"
                            />
                        </x-slot:actions>
                    @endif
                </x-list-item>
            @empty
                <p class="px-3 py-3 text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</p>
            @endforelse
            @auth
                @if (($isParticipant || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2 justify-end" data-ui="activity-show-participants-actions">
                        @if ($isParticipant)
                            <x-button type="button" class="btn-error" wire:click="leave">{{ __('ui.activities.leave') }}</x-button>
                        @elseif ($canJoin && ! $activity->requires_approval && ! $isFull)
                            <x-button type="button" class="btn-primary" wire:click="join">{{ __('ui.activities.join') }}</x-button>
                        @endif
                    </div>
                @endif
            @endauth
        </div>
        <div class="min-w-0 border-t border-base-300 pt-6 md:border-l md:border-t-0 md:pl-6 md:pt-0" data-ui="activity-show-waitlist">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_waitlist') }}</h3>
            @if ($activity->waitlist->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('ui.activities.waitlist_empty_hint') }}</p>
            @else
                <div class="">
                    @foreach ($activity->waitlist as $entry)
                        <x-list-item :item="$entry" :avatar="false" value="position" class="px-3 py-3">
                            <x-slot:value class="truncate text-sm font-medium text-base-content">
                                <div class="flex min-w-0 items-center gap-2">
                                    @if ($canManageActivity && $activity->requires_approval)
                                        <x-button
                                            type="button"
                                            class="btn-success btn-square btn-xs"
                                            icon="o-arrow-left"
                                            :title="__('ui.activities.approve_from_waitlist')"
                                            :aria-label="__('ui.activities.approve_from_waitlist')"
                                            wire:click="approveWaitlist({{ $entry->id }})"
                                            data-ui="activity-show-waitlist-approve"
                                        />
                                    @endif
                                    <x-user-badge
                                        :user="$entry->user"
                                        size="sm"
                                        name-class="truncate text-sm font-medium text-base-content"
                                        class="min-w-0 flex-1"
                                    />
                                </div>
                            </x-slot:value>
                        </x-list-item>
                    @endforeach
                </div>
            @endif
            @auth
                @if (($onWaitlist || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2 justify-end" data-ui="activity-show-waitlist-actions">
                        @if ($onWaitlist)
                            <x-button type="button" class="btn-neutral" wire:click="leaveWaitlist">{{ __('ui.activities.leave_waitlist') }}</x-button>
                        @elseif ($canJoin && ($activity->requires_approval || $isFull))
                            <x-button type="button" class="btn-primary" wire:click="joinWaitlist">{{ __('ui.activities.join_waitlist') }}</x-button>
                        @endif
                    </div>
                @endif
            @endauth
        </div>
    </div>
    @auth
        @if (filled($stateBlockedMessage ?? null))
            <p class="mb-4 text-sm text-error" data-ui="activity-show-state-blocked">{{ $stateBlockedMessage }}</p>
        @endif
        @if (($activeWindowRemainingForActivity ?? null) !== null)
            <p class="mb-2 text-sm text-base-content/80" data-ui="activity-show-window-activity-cap">
                {{ __('ui.events.enrollment_window_activity_spots_remaining', [
                    'remaining' => $activeWindowRemainingForActivity,
                    'max' => $activeWindowPerActivityMax,
                ]) }}
            </p>
        @endif
        @if (($activeWindowUserRemaining ?? null) !== null)
            <p class="mb-4 text-sm text-base-content/70" data-ui="activity-show-window-user-cap">
                {{ __('ui.events.enrollment_window_user_spots_remaining', ['remaining' => $activeWindowUserRemaining]) }}
            </p>
        @endif
        @if (filled($signupBlockedMessage ?? null) && ! $isParticipant && ! $onWaitlist && ! $canJoin)
            <p class="mb-4 text-sm text-error" data-ui="activity-show-signup-blocked">{{ $signupBlockedMessage }}</p>
        @endif
    @endauth
</div>
