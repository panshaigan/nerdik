@php
    $activityTypeSlug = $activity->activityType?->slug;
    $activityTypeLabel = $activityTypeSlug ? __('ui.activities.types.'.$activityTypeSlug) : __('ui.common.none');
    $slot = $activity->slot;
    $event = $slot?->event;
    $isCancelled = $activity->isCancelled();
    $hostRoleLabel = $activityTypeSlug && \Illuminate\Support\Facades\Lang::has('ui.activities.host_title.'.$activityTypeSlug)
        ? __('ui.activities.host_title.'.$activityTypeSlug)
        : __('ui.activities.host');
    $hasOpenRunBlurb = $slot && ! $event;
    $hostUser = $activity->creator;
    $participantsCounterValue = ((int) $activity->participants->count()).'/'.($activity->max_participants ?? '∞');
    $participationSlotsLabel = __('ui.activities.show_participation_section').' <span class="badge badge-primary badge-sm ml-2">'.$participantsCounterValue.'</span>';
    $showHeroHost = ! $activity->is_host_passive && $hostUser;
@endphp

<div class="relative" data-show-activity-id="{{ $activity->id }}" data-ui-activity-invite-context="{{ $activity->id }}">
    <x-listing-show-page-background
        :picture="$coverPicture"
        data-ui="activity-show-page-background"
    />
    <div class="relative z-0 space-y-4 sm:space-y-6">
    <x-page-header :title="$activity->name" :user="$activity->creator" :late-minutes="$hostLateMinutes ?? null">
        @if ($showHeroHost)
            <x-slot:subtitle>
                <span class="text-glow-base-100">{{ $activityTypeLabel }}</span>
                @if ($event)
                    <span class="text-glow-base-100">@</span>
                    <a
                        href="{{ route('events.show', $event) }}"
                        wire:navigate
                        class="link link-primary break-words text-glow-base-100"
                        data-ui="activity-show-hero-event-link"
                    >{{ $event->name }}</a>
                @endif
                @if ($activity->duration_in_minutes)
                    <span class="text-glow-base-100"><x-icon name="o-clock" class="inline h-4 w-4 align-text-bottom" />{{ $activity->duration_for_humans }}</span>
                @endif
            </x-slot:subtitle>
        @endif

        <x-slot:titleSuffix>
            @if ($activity->isCancelled())
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
                            @if (filled($activity->cancel_reason))
                                <p>
                                    <span class="font-semibold">{{ __('ui.activities.cancel_reason_label') }}:</span>
                                    <span class="mt-0.5 block">{{ $activity->cancel_reason }}</span>
                                </p>
                            @endif
                            <p>
                                <span class="font-semibold">{{ __('ui.events.cancellation_popover_who') }}:</span>
                                <span class="mt-0.5 block">{{ $activity->canceller?->displayName() ?? __('ui.common.unknown_user') }}</span>
                            </p>
                            <p>
                                <span class="font-semibold">{{ __('ui.events.cancellation_popover_when') }}:</span>
                                <span class="mt-0.5 block">{{ $activity->cancelled_at ? format_datetime_in_user_tz($activity->cancelled_at) : '—' }}</span>
                            </p>
                        </div>
                    </x-slot:content>
                </x-popover>
            @endif
        </x-slot:titleSuffix>

        <x-slot:info>
            <div data-ui="activity-show-info-section">
                <x-ui.activity-badge-group
                    :items="$badgeItems"
                    class="!my-0 min-h-[4.5rem] flex-1 items-center gap-2 rounded-2xl"
                    data-ui="activity-show-badge-group"
                />
                <div class="mt-2 flex items-end gap-3">
                    <div class="min-w-0 flex-1" data-ui="activity-show-participants-stat">
                        <x-stat
                            title="{{ __('ui.activities.show_participation_section') }}"
                            value="{{ $participantsCounterValue }}"
                            icon="o-users"
                            color="text-base-content"
                            class="ui-stat-embed ui-activity-show-stat"
                        />
                    </div>
                    <div class="ml-auto shrink-0 self-end" data-ui="activity-show-follow">
                        <x-ui.follow-interest-button
                            :has-interest="$hasInterest"
                            :count="$interestedPeopleCount"
                            data-ui-prefix="activity-show"
                        />
                    </div>
                </div>
            </div>
        </x-slot:info>
    </x-page-header>

    <div
            id="ui-activity-show-hero"
            class="ui-activity-show-hero ui-content-card relative min-h-[min(32rem,70dvh)] rounded-2xl mb-4 md:mb-6"
            data-ui="activity-show-hero"
        >
            <x-ui.tabs-with-toolbar
                wire:model.live.preserve-scroll="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="activity-show-tabs"
            >
                <x-slot:toolbar>
                    <div class="flex shrink-0 items-center gap-1" data-ui="activity-show-tabs-toolbar">
                        @if ($sharePayload)
                            <x-ui.share-menu :payload="$sharePayload" />
                        @endif
                        @if ($calendarPayload)
                            <x-ui.calendar-menu :payload="$calendarPayload" />
                        @endif
                        @auth
                            @if ($canAnnounceLate ?? false)
                                <x-ui.late-announce-menu
                                    :current-minutes="$viewerLateMinutes ?? null"
                                    wire-method="announceLate"
                                    data-ui="activity-show-late-announce"
                                />
                            @endif
                            @if ($canManageActivity || ($canManageEntityLinks ?? false))
                                <x-ui.overflow-menu
                                    icon="o-cog-6-tooth"
                                    :label="__('ui.common.manage')"
                                    panel-class="w-64"
                                    data-ui="activity-show-manage"
                                >
                                    @if ($canManageEntityLinks ?? false)
                                        <x-ui.overflow-menu-item
                                            icon="o-link"
                                            wire:click="openAddEntityLink"
                                            data-ui="activity-show-add-link"
                                        >
                                            {{ __('ui.entity_links.add_action') }}
                                        </x-ui.overflow-menu-item>
                                    @endif
                                    @if ($canManageActivity)
                                        <x-ui.overflow-menu-item
                                            icon="o-printer"
                                            :href="route('activities.participants.pdf', $activity)"
                                            external
                                            data-ui="activity-show-print-participants"
                                        >
                                            {{ __('ui.pdf.roster.print_action') }}
                                        </x-ui.overflow-menu-item>
                                        <x-ui.overflow-menu-item
                                            icon="o-pencil"
                                            :href="url_with_return(route('activities.edit', $activity))"
                                            data-ui="activity-show-edit"
                                        >
                                            {{ __('ui.common.edit') }}
                                        </x-ui.overflow-menu-item>
                                        <x-ui.overflow-menu-item
                                            icon="o-square-2-stack"
                                            :href="url_with_return(route('activities.create', ['duplicate' => $activity->slug]))"
                                            data-ui="activity-show-duplicate"
                                        >
                                            {{ __('ui.activities.duplicate_action') }}
                                        </x-ui.overflow-menu-item>
                                        @if ($canHardDeleteActivity ?? false)
                                            <x-ui.overflow-menu-item
                                                icon="o-trash"
                                                class="text-error"
                                                wire:click="confirmDeleteActivity"
                                                data-ui="activity-show-delete"
                                            >
                                                {{ __('ui.common.delete') }}
                                            </x-ui.overflow-menu-item>
                                        @endif
                                        @if ($isCancelled)
                                            <x-ui.overflow-menu-item
                                                icon="o-arrow-uturn-left"
                                                wire:click="confirmReopenActivity"
                                            >
                                                {{ __('ui.activities.reopen_action') }}
                                            </x-ui.overflow-menu-item>
                                        @else
                                            <x-ui.overflow-menu-item
                                                icon="o-x-circle"
                                                class="text-warning"
                                                wire:click="confirmCancelActivity"
                                            >
                                                {{ __('ui.activities.cancel_action') }}
                                            </x-ui.overflow-menu-item>
                                        @endif
                                    @endif
                                </x-ui.overflow-menu>
                            @endif
                        @endauth
                    </div>
                </x-slot:toolbar>
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="activity-show-tab-info" icon="o-light-bulb">
                    @include('livewire.activities.partials.show-about-tab', [
                        'activity' => $activity,
                        'scheduleMapConfig' => $schedule->scheduleMapConfig,
                        'scheduleVenue' => $schedule->scheduleVenue,
                        'scheduleRoom' => $schedule->scheduleRoom,
                        'schedulePlaceSummary' => $schedule->schedulePlaceSummary,
                        'scheduleDateSummary' => $schedule->scheduleDateSummary,
                    ])
                </x-tab>

                <x-tab name="participation" :label="$participationSlotsLabel" class="p-6 pt-4 sm:p-8 sm:pt-5" data-ui="activity-show-tab-participation" icon="o-users">
                    <div wire:key="activity-show-participation-{{ $activity->id }}-{{ $participationBroadcastRefreshTick }}">
                        @include('livewire.activities.partials.show-participation-tab', [
                            'activity' => $activity,
                            'isParticipant' => $isParticipant,
                            'onWaitlist' => $onWaitlist,
                            'canJoin' => $canJoin,
                            'canPromptGuestJoin' => $canPromptGuestJoin,
                            'isFull' => $isFull,
                            'canManageActivity' => $canManageActivity,
                            'canMarkParticipantsAbsent' => $canMarkParticipantsAbsent,
                            'canSetOthersLate' => $canSetOthersLate ?? false,
                            'hostLateMinutes' => $hostLateMinutes ?? null,
                            'stateBlockedMessage' => $stateBlockedMessage,
                            'signupBlockedMessage' => $signupBlockedMessage,
                            'activeWindowRemainingForActivity' => $activeWindowRemainingForActivity,
                            'activeWindowPerActivityMax' => $activeWindowPerActivityMax,
                            'activeWindowUserRemaining' => $activeWindowUserRemaining,
                            'isLotteryPending' => $isLotteryPending,
                            'isLotteryResolved' => $isLotteryResolved,
                            'lotteryDrawNotices' => $lotteryDrawNotices ?? [],
                        ])
                    </div>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>
    </div>

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    />

    @if ($canManageEntityLinks ?? false)
        <livewire:entity-links.manage-entity-links
            :linkable="$activity"
            :show-list="false"
            :listen-for-open-add="true"
            instance-suffix="shell"
            data-ui="activity-show-entity-links-shell"
            :key="'activity-entity-links-shell-'.$activity->id"
        />
    @endif

    @include('livewire.partials.familiarity-prompt-modal')
</div>
