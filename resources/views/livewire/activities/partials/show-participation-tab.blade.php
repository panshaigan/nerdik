@php
    $activityStartsAtGmt = $activity->hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED
        ? $activity->starts_at
        : $activity->slot?->starts_at;
    $canMarkAbsentNow = $activityStartsAtGmt !== null
        && $activityStartsAtGmt->clone()->utc()->lte(now('UTC'));
    $usesWaitlistSignup = $activity->isHostApprovalMode() || $activity->isLotteryMode();
    $canMarkParticipantsAbsent = $canMarkParticipantsAbsent ?? false;
    $canSetOthersLate = $canSetOthersLate ?? false;
    $hostUserId = (int) ($activity->created_by ?? 0);
    $showManagerHostLateRow = $canSetOthersLate && $activity->creator !== null && $hostUserId > 0;
    $nonHostParticipants = $showManagerHostLateRow
        ? $activity->participants->where('user_id', '!=', $hostUserId)->values()
        : $activity->participants;
@endphp
<div data-ui="activity-show-participation">
    <div class="mb-6 max-w-xl mx-auto w-full">
        @include('livewire.activities.partials.participation-notices', [
            'activity' => $activity,
            'noticesContainerDataUi' => 'activity-show-participation-notices',
            'noticeDataUiPrefix' => 'activity-show',
            'signupBlockedMessage' => $signupBlockedMessage ?? null,
            'stateBlockedMessage' => $stateBlockedMessage ?? null,
            'isParticipant' => $isParticipant,
            'onWaitlist' => $onWaitlist,
            'canJoin' => $canJoin,
            'activeWindowRemainingForActivity' => $activeWindowRemainingForActivity ?? null,
            'activeWindowPerActivityMax' => $activeWindowPerActivityMax ?? null,
            'activeWindowUserRemaining' => $activeWindowUserRemaining ?? null,
            'isLotteryPending' => $isLotteryPending ?? false,
            'isLotteryResolved' => $isLotteryResolved ?? false,
            'lotteryDrawNotices' => $lotteryDrawNotices ?? [],
        ])
    </div>
    <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="activity-show-participation-columns">
        <div class="min-w-0" data-ui="activity-show-participants">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
                @if ($canManageActivity)
                    <livewire:user-requests.invite-user-request
                        type="activity_invite"
                        :subject-id="$activity->id"
                        data-ui="activity-invite-participant"
                        :key="'invite-activity-'.$activity->id"
                    />
                @endif
            </div>
            @if ($showManagerHostLateRow)
                <div class="ui-participant-list-item flex items-center justify-between gap-2 px-3 py-3 max-sm:flex-col max-sm:items-stretch" data-ui="activity-show-host-late-row">
                    <div class="min-w-0 flex-1">
                        <x-user-badge
                            :user="$activity->creator"
                            :organization="$activity->organization"
                            size="sm"
                            :subline="__('ui.activities.host')"
                            :late-minutes="$hostLateMinutes ?? null"
                            name-class="truncate text-sm font-medium text-base-content"
                            class="min-w-0"
                            :context-activity-id="$canManageActivity ? $activity->id : null"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <x-ui.late-announce-menu
                            :current-minutes="$hostLateMinutes ?? null"
                            :target-user-id="$hostUserId"
                            wire-method="setParticipantLate"
                            data-ui="activity-show-host-late"
                        />
                    </div>
                </div>
            @endif
            @forelse ($nonHostParticipants as $p)
                @php
                    $participantRowIndex = $showManagerHostLateRow ? $loop->iteration : $loop->index;
                @endphp
                <x-list-item
                    :item="$p"
                    :avatar="false"
                    value="id"
                    @class([
                        'ui-participant-list-item px-3 py-3 max-sm:flex-col max-sm:items-stretch max-sm:gap-2',
                        'bg-base-200/40' => $participantRowIndex % 2 === 1,
                    ])
                >
                    <x-slot:value class="min-w-0 text-sm text-base-content">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-user-badge
                                    :user="$p->user"
                                    size="sm"
                                    :subline="((int) $p->user_id === $hostUserId ? __('ui.activities.host') : null)"
                                    :late-minutes="$p->late_minutes"
                                    name-class="truncate text-sm font-medium text-base-content"
                                    class="min-w-0 flex-1"
                                    :context-activity-id="$canManageActivity ? $activity->id : null"
                                />
                                @if ($p->is_absent)
                                    <span class="badge badge-warning badge-sm shrink-0">{{ __('ui.activities.absent') }}</span>
                                @endif
                            </div>
                            @if ($canManageActivity)
                                <x-activity.familiarity-summary :familiarity="$p->familiarity" />
                            @endif
                    </x-slot:value>
                    @if (($canManageActivity || $canMarkParticipantsAbsent || $canSetOthersLate) && (int) $p->user_id !== $hostUserId)
                        <x-slot:actions class="flex flex-wrap items-center gap-1 max-sm:w-full">
                            @if ($canSetOthersLate)
                                <x-ui.late-announce-menu
                                    :current-minutes="$p->late_minutes"
                                    :target-user-id="$p->user_id"
                                    wire-method="setParticipantLate"
                                    data-ui="activity-show-participant-late-{{ $p->id }}"
                                />
                            @endif
                            @if ($canMarkParticipantsAbsent)
                                @if ($p->is_absent)
                                    <x-button
                                        type="button"
                                        class="btn btn-sm text-success"
                                        :title="__('ui.activities.unmark_absent')"
                                        :aria-label="__('ui.activities.unmark_absent')"
                                        wire:click="unmarkParticipantAbsent({{ $p->id }})"
                                        :spinner="'unmarkParticipantAbsent('.$p->id.')'"
                                    >{{ __('ui.activities.unmark_absent') }}</x-button>
                                @else
                                    @if ($canMarkAbsentNow)
                                        <x-button
                                            type="button"
                                            class="btn btn-sm text-warning"
                                            :title="__('ui.activities.mark_absent')"
                                            :aria-label="__('ui.activities.mark_absent')"
                                            wire:click="markParticipantAbsent({{ $p->id }})"
                                            :spinner="'markParticipantAbsent('.$p->id.')'"
                                        >{{ __('ui.activities.mark_absent') }}</x-button>
                                    @endif
                                @endif
                            @endif
                            @if ($canManageActivity)
                                <x-button
                                    type="button"
                                    class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                    :title="__('ui.activities.remove_participant')"
                                    :aria-label="__('ui.activities.remove_participant')"
                                    wire:click="confirmRemoveParticipant({{ $p->id }})"
                                    :spinner="'confirmRemoveParticipant('.$p->id.')'"
                                    icon="o-trash"
                                />
                                @if ($activity->isHostApprovalMode())
                                <x-button
                                    type="button"
                                    class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                    icon="o-arrow-right"
                                    :title="__('ui.activities.move_to_waitlist')"
                                    :aria-label="__('ui.activities.move_to_waitlist')"
                                    wire:click="confirmMoveParticipantToWaitlist({{ $p->id }})"
                                    :spinner="'confirmMoveParticipantToWaitlist('.$p->id.')'"
                                />
                                @endif
                            @endif
                        </x-slot:actions>
                    @endif
                </x-list-item>
            @empty
                @if (! $showManagerHostLateRow)
                    <p class="text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</p>
                @endif
            @endforelse
            @auth
                @if (($isParticipant || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2" data-ui="activity-show-participants-actions">
                        @if ($isParticipant)
                            <x-button type="button" class="btn-error mx-auto" wire:click="leave" spinner="leave">{{ __('ui.activities.leave') }}</x-button>
                        @elseif ($canJoin && $activity->isOpenParticipationMode() && ! $isFull)
                            <x-button type="button" class="btn-primary mx-auto" wire:click="join" spinner="join">{{ __('ui.activities.join') }}</x-button>
                        @endif
                    </div>
                @endif
            @else
                @if (($canPromptGuestJoin ?? false) && $activity->isOpenParticipationMode() && ! $isFull && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2" data-ui="activity-show-participants-actions">
                        <x-button
                            :link="login_url(route('activities.show', ['activity' => $activity, 'tab' => 'participation'], false))"
                            :no-wire-navigate="true"
                            class="btn-primary mx-auto"
                            data-ui="activity-show-guest-join"
                        >{{ __('ui.activities.join') }}</x-button>
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
                        <x-list-item
                            :item="$entry"
                            :avatar="false"
                            value="position"
                            @class([
                                'ui-participant-list-item px-3 py-3',
                                'bg-base-200/40' => $loop->even,
                            ])
                        >
                            <x-slot:value class="min-w-0 text-sm text-base-content">
                                <div class="flex min-w-0 items-center gap-2">
                                    @if ($canManageActivity && $activity->isHostApprovalMode())
                                        <x-button
                                            type="button"
                                            class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
                                            icon="o-arrow-left"
                                            :title="__('ui.activities.approve_from_waitlist')"
                                            :aria-label="__('ui.activities.approve_from_waitlist')"
                                            wire:click="approveWaitlist({{ $entry->id }})"
                                            :spinner="'approveWaitlist('.$entry->id.')'"
                                            data-ui="activity-show-waitlist-approve"
                                        />
                                    @endif
                                    <x-user-badge
                                        :user="$entry->user"
                                        size="sm"
                                        name-class="truncate text-sm font-medium text-base-content"
                                        class="min-w-0 flex-1"
                                        :context-activity-id="$canManageActivity ? $activity->id : null"
                                    />
                                </div>
                                @if ($canManageActivity)
                                    <x-activity.familiarity-summary :familiarity="$entry->familiarity" />
                                @endif
                            </x-slot:value>
                        </x-list-item>
                    @endforeach
                </div>
            @endif
            @auth
                @if (($onWaitlist || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2 justify-end" data-ui="activity-show-waitlist-actions">
                        @if ($onWaitlist)
                            <x-button type="button" class="btn-neutral" wire:click="leaveWaitlist" spinner="leaveWaitlist">{{ __('ui.activities.leave_waitlist') }}</x-button>
                        @elseif ($canJoin && ($usesWaitlistSignup || $isFull))
                            <x-button type="button" class="btn-primary" wire:click="joinWaitlist" spinner="joinWaitlist">{{ __('ui.activities.join_waitlist') }}</x-button>
                        @endif
                    </div>
                @endif
            @else
                @if (($canPromptGuestJoin ?? false) && ($usesWaitlistSignup || $isFull) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-6 flex flex-wrap gap-2 justify-end" data-ui="activity-show-waitlist-actions">
                        <x-button
                            :link="login_url(route('activities.show', ['activity' => $activity, 'tab' => 'participation'], false))"
                            :no-wire-navigate="true"
                            class="btn-primary"
                            data-ui="activity-show-guest-join-waitlist"
                        >{{ __('ui.activities.join_waitlist') }}</x-button>
                    </div>
                @endif
            @endauth
        </div>
    </div>
</div>
