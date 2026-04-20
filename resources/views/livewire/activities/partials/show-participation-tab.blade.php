<div data-ui="activity-show-participation">
    <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="activity-show-participation-columns">
        <div class="min-w-0 md:pr-6" data-ui="activity-show-participants">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
            <div class="">
                @forelse ($activity->participants as $p)
                    <x-list-item :item="$p" :avatar="false" value="id" sub-value="id" class="px-3 py-3">
                        <x-slot:value class="truncate text-sm font-medium text-base-content">
                            <x-user-badge
                                :user="$p->user"
                                size="sm"
                                :subline="((int) $p->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                                name-class="truncate text-sm font-medium text-base-content"
                                class="min-w-0 flex-1"
                            />
                        </x-slot:value>
                        <x-slot:sub-value class="truncate text-xs text-base-content/65">
                            @if ((int) $p->user_id === (int) ($activity->created_by ?? 0))
                                {{ __('ui.activities.host') }}
                            @elseif ($p->is_absent)
                                {{ __('ui.activities.absent') }}
                            @endif
                        </x-slot:sub-value>
                        @if ($canManageActivity && (int) $p->user_id !== (int) ($activity->created_by ?? 0))
                            <x-slot:actions class="flex items-center gap-1">
                                @if ($p->is_absent)
                                    <form action="{{ route('activity-participants.unmark-absent', $p) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button
                                            type="submit"
                                            class="btn-ghost btn-square btn-sm text-success"
                                            :title="__('ui.activities.unmark_absent')"
                                            :aria-label="__('ui.activities.unmark_absent')"
                                        >↺</x-button>
                                    </form>
                                @else
                                    <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button
                                            type="submit"
                                            class="btn-ghost btn-sm text-warning"
                                            :title="__('ui.activities.mark_absent')"
                                            :aria-label="__('ui.activities.mark_absent')"
                                        >{{ __('ui.activities.mark_absent') }}</x-button>
                                    </form>
                                @endif
                                <form
                                    action="{{ route('activity-participants.remove', $p) }}"
                                    method="POST"
                                    class="inline"
                                >
                                    @csrf
                                    <x-button
                                        type="submit"
                                        class="btn-ghost btn-square btn-xs text-error"
                                        :title="__('ui.activities.remove_participant')"
                                        :aria-label="__('ui.activities.remove_participant')"
                                    >
                                        <x-ui.icons.trash class="h-4 w-4 shrink-0" />
                                    </x-button>
                                </form>
                                <form
                                    action="{{ route('activity-participants.move-to-waitlist', $p) }}"
                                    method="POST"
                                    class="inline"
                                    onsubmit='return window.confirm({!! json_encode(__('ui.activities.move_to_waitlist_confirm'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!})'
                                >
                                    @csrf
                                    <x-button
                                        type="submit"
                                        class="btn-ghost btn-square btn-xs"
                                        :title="__('ui.activities.move_to_waitlist')"
                                        :aria-label="__('ui.activities.move_to_waitlist')"
                                    >
                                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </x-button>
                                </form>
                            </x-slot:actions>
                        @endif
                    </x-list-item>
                @empty
                    <p class="px-3 py-3 text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</p>
                @endforelse
            </div>
            @auth
                @if (($isParticipant || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-3 flex flex-wrap gap-2" data-ui="activity-show-participants-actions">
                        @if ($isParticipant)
                            <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-error">{{ __('ui.activities.leave') }}</x-button>
                            </form>
                        @elseif ($canJoin && ! $activity->requires_approval && ! $isFull)
                            <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-primary">{{ __('ui.activities.join') }}</x-button>
                            </form>
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
                                <x-user-badge
                                    :user="$p->user"
                                    size="sm"
                                    :subline="((int) $p->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                                    name-class="truncate text-sm font-medium text-base-content"
                                    class="min-w-0 flex-1"
                                />
                            </x-slot:value>
                            @if ($canManageActivity && $activity->requires_approval)
                                <x-slot:actions>
                                    <form
                                        action="{{ route('activities.waitlist.approve', [$activity, $entry]) }}"
                                        method="POST"
                                        class="inline shrink-0"
                                        data-ui="activity-show-waitlist-approve"
                                    >
                                        @csrf
                                        <x-button type="submit" class="btn-primary btn-sm">{{ __('ui.activities.approve_from_waitlist') }}</x-button>
                                    </form>
                                </x-slot:actions>
                            @endif
                        </x-list-item>
                    @endforeach
                </div>
            @endif
            @auth
                @if (($onWaitlist || $canJoin) && ! filled($stateBlockedMessage ?? null))
                    <div class="mt-3 flex flex-wrap gap-2" data-ui="activity-show-waitlist-actions">
                        @if ($onWaitlist)
                            <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-neutral">{{ __('ui.activities.leave_waitlist') }}</x-button>
                            </form>
                        @elseif ($canJoin && ($activity->requires_approval || $isFull))
                            <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-warning">{{ __('ui.activities.join_waitlist') }}</x-button>
                            </form>
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
