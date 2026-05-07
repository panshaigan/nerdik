<div class="space-y-6 pt-2" data-ui="event-activity-preview-participation">
    @auth
        <div class="mx-auto w-full max-w-xl space-y-2">
            @if (filled($participation?->signupBlockedMessage) && ! $participation?->isParticipant && ! $participation?->onWaitlist && ! $participation?->canJoin)
                <x-alert
                    title="Hey!"
                    description="{{ $participation->signupBlockedMessage }}"
                    icon="o-home"
                    data-ui="event-activity-preview-signup-blocked"
                    class="alert-neutral"
                />
            @endif

            @if (filled($participation?->stateBlockedMessage))
                <x-alert
                    title="Hey!"
                    description="{{ $participation->stateBlockedMessage }}"
                    icon="o-home"
                    data-ui="event-activity-preview-state-blocked"
                    class="alert-neutral"
                />
            @endif

            @if (($participation?->activeWindowRemainingForActivity ?? null) !== null)
                <x-alert
                    title="Hey!"
                    description="{{ __('ui.events.enrollment_window_activity_spots_remaining', [
                        'remaining' => $participation->activeWindowRemainingForActivity,
                        'max' => $participation->activeWindowPerActivityMax,
                    ]) }}"
                    icon="o-home"
                    data-ui="event-activity-preview-window-activity-cap"
                    class="alert-neutral"
                />
            @endif

            @if (($participation?->activeWindowUserRemaining ?? null) !== null)
                <x-alert
                    title="Hey!"
                    description="{{ __('ui.events.enrollment_window_user_spots_remaining', ['remaining' => $participation->activeWindowUserRemaining]) }}"
                    icon="o-home"
                    data-ui="event-activity-preview-window-user-cap"
                    class="alert-neutral"
                />
            @endif
        </div>
    @endauth

    <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="event-activity-preview-participation-columns">
        <div class="min-w-0" data-ui="event-activity-preview-participants">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
            @forelse ($activity->participants as $participant)
                <x-list-item :item="$participant" :avatar="false" value="id" sub-value="id" class="px-3 py-3">
                    <x-slot:value class="truncate text-sm font-medium text-base-content">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-user-badge
                                :user="$participant->user"
                                size="sm"
                                :subline="((int) $participant->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                                name-class="truncate text-sm font-medium text-base-content"
                                class="min-w-0 flex-1"
                            />
                            @if ($participant->is_absent)
                                <span class="badge badge-warning badge-sm shrink-0">{{ __('ui.activities.absent') }}</span>
                            @endif
                        </div>
                    </x-slot:value>
                </x-list-item>
            @empty
                <p class="text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</p>
            @endforelse
        </div>

        <div class="min-w-0 border-t border-base-300 pt-6 md:border-l md:border-t-0 md:pl-6 md:pt-0" data-ui="event-activity-preview-waitlist">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_waitlist') }}</h3>
            @if ($activity->waitlist->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('ui.activities.waitlist_empty_hint') }}</p>
            @else
                <div>
                    @foreach ($activity->waitlist as $entry)
                        <x-list-item :item="$entry" :avatar="false" value="position" class="px-3 py-3">
                            <x-slot:value class="truncate text-sm font-medium text-base-content">
                                <x-user-badge
                                    :user="$entry->user"
                                    size="sm"
                                    name-class="truncate text-sm font-medium text-base-content"
                                    class="min-w-0 flex-1"
                                />
                            </x-slot:value>
                        </x-list-item>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
