<div data-ui="activity-show-participation">
    <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="activity-show-participation-columns">
        <div class="min-w-0 md:pr-6" data-ui="activity-show-participants">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
            <ul class="divide-y divide-base-300">
                @forelse ($activity->participants as $p)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                        <x-user-badge
                            :user="$p->user"
                            size="sm"
                            :subline="((int) $p->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                            name-class="truncate text-sm font-medium text-base-content"
                            class="min-w-0 flex-1"
                        />
                        @if ($p->is_absent)
                            <span class="text-xs font-medium text-error">({{ __('ui.activities.absent') }})</span>
                        @endif
                        @if ($canManageActivity && (int) $p->user_id !== (int) ($activity->created_by ?? 0))
                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                                @if ($p->is_absent)
                                    <form action="{{ route('activity-participants.unmark-absent', $p) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-ghost btn-xs text-success">{{ __('ui.activities.unmark_absent') }}</x-button>
                                    </form>
                                @else
                                    <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-ghost btn-xs text-error">{{ __('ui.activities.mark_absent') }}</x-button>
                                    </form>
                                @endif
                                <form
                                    action="{{ route('activity-participants.move-to-waitlist', $p) }}"
                                    method="POST"
                                    class="inline"
                                    onsubmit='return window.confirm({!! json_encode(__('ui.activities.move_to_waitlist_confirm'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!})'
                                >
                                    @csrf
                                    <x-button type="submit" class="btn-ghost btn-xs">{{ __('ui.activities.move_to_waitlist') }}</x-button>
                                </form>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="min-w-0 border-t border-base-300 pt-6 md:border-l md:border-t-0 md:pl-6 md:pt-0" data-ui="activity-show-waitlist">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_waitlist') }}</h3>
            @if ($activity->waitlist->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('ui.activities.waitlist_empty_hint') }}</p>
            @else
                <ul class="divide-y divide-base-300">
                    @foreach ($activity->waitlist as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="tabular-nums text-xs text-base-content/60">#{{ $entry->position }}</span>
                                <x-user-badge
                                    :user="$entry->user"
                                    size="sm"
                                    name-class="truncate text-sm font-medium text-base-content"
                                />
                            </div>
                            @if ($canManageActivity && $activity->requires_approval)
                                <form
                                    action="{{ route('activities.waitlist.approve', [$activity, $entry]) }}"
                                    method="POST"
                                    class="inline shrink-0"
                                    data-ui="activity-show-waitlist-approve"
                                >
                                    @csrf
                                    <x-button type="submit" class="btn-primary btn-sm">{{ __('ui.activities.approve_from_waitlist') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 pb-4">
        <div class="flex flex-wrap items-center justify-end gap-3">
            @auth
                @if (($isParticipant || $onWaitlist || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="flex flex-wrap gap-2" data-ui="activity-show-participation-actions">
                        @if ($isParticipant)
                            <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-error">{{ __('ui.activities.leave') }}</x-button>
                            </form>
                        @elseif ($onWaitlist)
                            <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-neutral">{{ __('ui.activities.leave_waitlist') }}</x-button>
                            </form>
                        @elseif ($canJoin)
                            @if ($activity->requires_approval || $isFull)
                                <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-warning">{{ __('ui.activities.join_waitlist') }}</x-button>
                                </form>
                            @else
                                <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-primary">{{ __('ui.activities.join') }}</x-button>
                                </form>
                            @endif
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
