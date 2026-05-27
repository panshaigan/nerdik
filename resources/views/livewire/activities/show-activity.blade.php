@php
    $logoUrl = $activity->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($activity->logo_path)
        : null;
    $activityTypeSlug = $activity->activityType?->slug;
    $activityTypeLabel = $activityTypeSlug ? __('ui.activities.types.'.$activityTypeSlug) : __('ui.common.none');
    $slot = $activity->slot;
    $event = $slot?->event;
    $selfHosted = $activity->hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED;
    $isCancelled = $activity->isCancelled();
    $selfHostedPlace = $activity->place;
    $hostRoleLabel = $activityTypeSlug && \Illuminate\Support\Facades\Lang::has('ui.activities.host_title.'.$activityTypeSlug)
        ? __('ui.activities.host_title.'.$activityTypeSlug)
        : __('ui.activities.host');
    $hasOpenRunBlurb = $slot && ! $event;
    $hostUser = $activity->creator;
    $slotPlace = $slot?->place;
    $schedulePlace = $selfHosted ? $selfHostedPlace : $slotPlace;
    $scheduleVenue = $schedulePlace?->parent ?? $schedulePlace;
    $scheduleRoom = $schedulePlace?->parent ? $schedulePlace->name : null;
    $scheduleStartsAt = $selfHosted ? $activity->starts_at : $slot?->starts_at;
    $scheduleEndsAt = $selfHosted ? $activity->ends_at : $slot?->ends_at;
    $scheduleDateSummary = $scheduleStartsAt && $scheduleEndsAt
        ? format_datetime_range_compact($scheduleStartsAt, $scheduleEndsAt)
        : ($scheduleStartsAt ? format_in_user_tz($scheduleStartsAt, 'D, M j · H:i') : null);
    $scheduleMapConfig = [
        'places' => ($scheduleVenue && $scheduleVenue->latitude !== null && $scheduleVenue->longitude !== null)
            ? [[
                'name' => (string) $scheduleVenue->name,
                'lat' => (float) $scheduleVenue->latitude,
                'lng' => (float) $scheduleVenue->longitude,
            ]]
            : [],
    ];
    $participantsCounterValue = ((int) $activity->participants->count()).'/'.($activity->max_participants ?? '∞');
    $participationSlotsLabel = __('ui.activities.show_participation_section').' <span class="badge badge-primary badge-sm ml-2">'.$participantsCounterValue.'</span>';
    $venues = $event?->places->map(
        fn ($place) => (string) $place->name.', '.$place->city->name(app()->getLocale())
    )->join('; ');
    $showHeroHost = ! $activity->is_host_passive && $hostUser;
@endphp

<div class="space-y-2 sm:space-y-4 " data-show-activity-id="{{ $activity->id }}">
    <x-page-header :title="$activity->name" :user="$activity->creator">
        @if ($showHeroHost)
            <x-slot:subtitle>
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                    {{ $activityTypeLabel }}
                    @if ($event)
                        @
                        <a
                            href="{{ route('events.show', $event) }}"
                            wire:navigate
                            class="link link-primary break-words"
                            data-ui="activity-show-hero-event-link"
                        >{{ $event->name }}</a>
                    @endif
                    @if ($activity->duration_in_minutes)
                        <x-icon name="o-clock" class="inline h-4 w-4 align-text-bottom" />{{ $activity->duration_for_humans }}
                    @endif
                </p>
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
                                <span class="mt-0.5 block">{{ $activity->cancelled_at ? format_datetime_in_user_tz($event->cancelled_at) : '—' }}</span>
                            </p>
                        </div>
                    </x-slot:content>
                </x-popover>
            @endif
        </x-slot:titleSuffix>
    </x-page-header>

    <div class="grid grid-cols-1 items-center gap-3 px-3 pb-5 sm:px-3 sm:pb-6 sm:grid-cols-4">
        <x-ui.activity-badge-group
            :items="$badgeItems"
            class="col-span-3 bg-texture-glass box-glow-primary !rounded-2xl p-6"
            data-ui="activity-show-badge-group"
        />
        <div class="mx-auto grid grid-cols-2 gap-3 mt-2 sm:mt-0">
            <div class="mx-auto box-glow-dark-primary rounded-2xl px-4 py-3">
                <x-stat
                    title="{{ __('ui.activities.show_participation_section') }}"
                    value="{{ $participantsCounterValue }}"
                    icon="o-users"
                    class="ui-stat-embed"
                    data-ui="activity-show-participants-stat"
                />
            </div>
            <x-ui.interested-stat-card
                :title="__('ui.events.interested_people_count')"
                :value="$interestedPeopleCount"
                :has-interest="$hasInterest"
                data-ui="activity-show-interested-stat"
            />
        </div>
    </div>

    <div
            class="ui-activity-show-hero rounded-xl ui-content-card mt-6"
            data-ui="activity-show-hero"
        >
            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 sm:px-3"
                data-ui="activity-show-tabs"
                class="bg-texture-scratches rounded-2xl"
            >
                <x-slot:toolbar>
                    @auth
                        <div class="flex shrink-0 items-center gap-1" data-ui="activity-show-tabs-toolbar">
                            @if ($canManageActivity)
                                <x-button
                                    :link="url_with_return(route('activities.edit', $activity))"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :tooltip="__('ui.activities.edit')"
                                    :aria-label="__('ui.activities.edit').': '.$activity->name"
                                    data-ui="activity-show-edit"
                                    icon="o-pencil"
                                />
                                <x-button
                                    :link="url_with_return(route('activities.create', ['duplicate' => $activity->slug]))"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :tooltip="__('ui.activities.duplicate_action')"
                                    :aria-label="__('ui.activities.duplicate_action').': '.$activity->name"
                                    data-ui="activity-show-duplicate"
                                    icon="o-square-2-stack"
                                />
                                @if ($canHardDeleteActivity ?? false)
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                        :tooltip="__('ui.activities.delete')"
                                        :aria-label="__('ui.activities.delete').': '.$activity->name"
                                        wire:click="confirmDeleteActivity"
                                        data-ui="activity-show-delete"
                                        icon="o-trash"
                                    />
                                @endif
                                @if ($isCancelled)
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
                                        :tooltip="__('ui.activities.reopen_action')"
                                        :aria-label="__('ui.activities.reopen_action')"
                                        wire:click="confirmReopenActivity"
                                        icon="o-arrow-uturn-left"
                                    />
                                @else
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                        :tooltip="__('ui.activities.cancel_action')"
                                        :aria-label="__('ui.activities.cancel_action')"
                                        wire:click="confirmCancelActivity"
                                        icon="o-x-circle"
                                    />
                                @endif
                            @endif
                        </div>
                    @endauth
                </x-slot:toolbar>
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="activity-show-tab-info" icon="o-light-bulb">
                    @include('livewire.activities.partials.show-about-tab', [
                        'activity' => $activity,
                        'scheduleMapConfig' => $scheduleMapConfig,
                        'scheduleVenue' => $scheduleVenue,
                        'scheduleRoom' => $scheduleRoom,
                        'scheduleDateSummary' => $scheduleDateSummary,
                    ])
                </x-tab>

                <x-tab name="participation" :label="$participationSlotsLabel" class="p-6 pt-4 sm:p-8 sm:pt-5" data-ui="activity-show-tab-participation" icon="o-users">
                    @include('livewire.activities.partials.show-participation-tab', [
                        'activity' => $activity,
                        'isParticipant' => $isParticipant,
                        'onWaitlist' => $onWaitlist,
                        'canJoin' => $canJoin,
                        'isFull' => $isFull,
                        'canManageActivity' => $canManageActivity,
                        'stateBlockedMessage' => $stateBlockedMessage,
                        'signupBlockedMessage' => $signupBlockedMessage,
                        'activeWindowRemainingForActivity' => $activeWindowRemainingForActivity,
                        'activeWindowPerActivityMax' => $activeWindowPerActivityMax,
                        'activeWindowUserRemaining' => $activeWindowUserRemaining,
                    ])
                </x-tab>
            </x-ui.tabs-with-toolbar>
</div>

    <x-ui.confirm-modal
        wire:model="confirmModalOpen"
        :title="$confirmModalTitle"
        :message="$confirmModalMessage"
        confirm-action="runConfirmedAction"
    />
</div>
