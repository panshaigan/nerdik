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
    $participationSlotsLabel = __('ui.activities.show_participation_section').' <span class="badge badge-primary badge-sm ml-2">'.((int) $activity->participants->count()).'/'.($activity->max_participants ?? '∞').'</span>';
@endphp

<div class="py-10 sm:py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if ($isCancelled)
            <div role="alert" class="alert alert-warning text-sm">
                <div class="space-y-1">
                    <p class="font-medium">{{ __('ui.activities.cancelled_badge') }}</p>
                    @if ($activity->cancel_reason)
                        <p>{{ __('ui.activities.cancel_reason_label') }}: {{ $activity->cancel_reason }}</p>
                    @endif
                    <p class="opacity-80">
                        {{ __('ui.activities.cancelled_meta', [
                            'who' => $activity->canceller?->nickname ?? $activity->canceller?->email ?? __('ui.common.unknown_user'),
                            'when' => $activity->cancelled_at ? format_datetime_in_user_tz($activity->cancelled_at) : '—',
                        ]) }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Hero --}}
        <div
            class="ui-activity-show-hero overflow-hidden rounded-xl border border-base-300 bg-base-100 shadow"
            data-ui="activity-show-hero"
        >
            <div class="relative min-h-[140px] bg-gradient-to-br from-primary/20 via-base-200/50 to-base-100 sm:min-h-[180px]">
                @if ($logoUrl)
                    <div class="absolute inset-0 opacity-30">
                        <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover" />
                    </div>
                @endif
                <div class="relative z-10 p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4 sm:gap-6" dir="ltr">
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ $activityTypeLabel }}
                                @if ($event)
                                    @
                                    <a
                                        href="{{ route('events.show', $event) }}"
                                        wire:navigate
                                        class="link link-primary break-words text-end"
                                        data-ui="activity-show-hero-event-link"
                                    >{{ $event->name }}</a>
                                @endif
                            </p>
                            <h1 class="text-2xl font-semibold leading-tight text-base-content sm:text-3xl pb-4">
                                {{ $activity->name }}
                            </h1>
                            <x-ui.activity-badge-group
                                :items="$badgeItems"
                                class="pt-0.5"
                                data-ui="activity-show-badge-group"
                            />
                        </div>
                        @if ((! $activity->is_host_passive && $hostUser) || $activity->duration_in_minutes || $event)
                            <div
                                class="flex min-w-0 max-w-[min(100%,14rem)] shrink-0 flex-col items-end gap-3 text-end sm:max-w-[16rem]"
                                data-ui="activity-show-hero-meta"
                            >
                                @if (! $activity->is_host_passive && $hostUser)
                                    <div class="text-sm">
                                        <p class="block text-xs leading-tight text-base-content/60">{{ $hostRoleLabel }}</p>
                                        <x-user-badge
                                            :user="$hostUser"
                                            size="md"
                                            class="mt-1 badge badge-primary"
                                            name-class="truncate text-end font-semibold"
                                            data-ui="activity-show-host"
                                        />
                                    </div>
                                @endif
                                @if ($activity->duration_in_minutes)
                                    <div class="text-sm">
                                        <p class="mt-1 block font-medium tabular-nums text-base-content"><x-icon name="o-clock" />{{ $activity->duration_for_humans }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 pb-2 pt-2 sm:px-3"
                data-ui="activity-show-tabs"
            >
                <x-slot:toolbar>
                    @auth
                        <div class="flex shrink-0 items-center gap-1" data-ui="activity-show-tabs-toolbar">
                            @if ($canManageActivity)
                                <x-button
                                    :link="route('activities.edit', $activity)"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :title="__('ui.activities.edit')"
                                    :aria-label="__('ui.activities.edit').': '.$activity->name"
                                    data-ui="activity-show-edit"
                                    icon="o-pencil"
                                />
                                <x-button
                                    type="button"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                    :title="__('ui.activities.delete')"
                                    :aria-label="__('ui.activities.delete').': '.$activity->name"
                                    wire:click="confirmDeleteActivity"
                                    data-ui="activity-show-delete"
                                    icon="o-trash"
                                />
                                @if ($isCancelled)
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
                                        :title="__('ui.activities.reopen_action')"
                                        :aria-label="__('ui.activities.reopen_action')"
                                        wire:click="confirmReopenActivity"
                                        icon="o-arrow-uturn-left"
                                    />
                                @else
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                        :title="__('ui.activities.cancel_action')"
                                        :aria-label="__('ui.activities.cancel_action')"
                                        wire:click="confirmCancelActivity"
                                        icon="o-x-circle"
                                    />
                                @endif
                            @endif
                            <x-button
                                :link="route('activities.create', ['duplicate' => $activity->slug])"
                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                :title="__('ui.activities.duplicate_action')"
                                :aria-label="__('ui.activities.duplicate_action').': '.$activity->name"
                                data-ui="activity-show-duplicate"
                                icon="o-square-2-stack"
                            />
                            @if ($hasInterest)
                                <x-button
                                    type="button"
                                    wire:click="removeInterest"
                                    class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove"
                                    :title="__('ui.interests.remove_from_interests')"
                                    data-ui="activity-show-interest-remove"
                                    icon="s-star"
                                />
                            @else
                                <x-button
                                    type="button"
                                    wire:click="addInterest"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning ui-action ui-action-interest-add"
                                    :title="__('ui.interests.add_to_interests')"
                                    data-ui="activity-show-interest-add"
                                    icon="o-star"
                                />
                            @endif
                        </div>
                    @endauth
                </x-slot:toolbar>
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="activity-show-tab-info" icon="o-book-open">
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
</div>
