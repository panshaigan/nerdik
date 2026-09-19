@if ($previewActivity)
    @php
        $previewParticipantCount = (int) ($previewActivity->participants_count ?? 0);
        $previewParticipationLabel = __('ui.activities.show_participation_section')
            .' <span class="badge badge-primary badge-sm ml-2">'
            .$previewParticipantCount.'/'.($previewActivity->max_participants ?? '∞')
            .'</span>';
        $previewCanJoinWaitlist = $previewActivityParticipation?->canJoin
            && ($previewActivity->isHostApprovalMode() || $previewActivity->isLotteryMode() || $previewActivityParticipation?->isFull);
        $previewCanJoinDirectly = $previewActivityParticipation?->canJoin
            && $previewActivity->isOpenParticipationMode()
            && ! $previewActivityParticipation?->isFull;
        $previewCanPromptGuestJoinWaitlist = $previewActivityParticipation?->canPromptGuestJoin
            && ($previewActivity->isHostApprovalMode() || $previewActivity->isLotteryMode() || $previewActivityParticipation?->isFull);
        $previewCanPromptGuestJoinDirectly = $previewActivityParticipation?->canPromptGuestJoin
            && $previewActivity->isOpenParticipationMode()
            && ! $previewActivityParticipation?->isFull;
        $previewGuestLoginUrl = login_url(browsing_return_url());
    @endphp

    <x-modal
        wire:model="activityPreviewModalOpen"
        :title="$previewActivity->name"
        box-class="ui-modal-surface ui-overlay-shell ui-overlay-sheet"
        class="backdrop-blur modal-bottom md:modal-end"
        separator
        data-ui="overlay-sheet"
    >
        <div
            wire:key="event-activity-preview-{{ $previewActivity->id }}-{{ $activityPreviewRefreshTick }}"
            class="flex min-h-0 flex-1 flex-col"
            data-ui="event-activity-preview-modal"
        >
            <x-ui.tabs-with-toolbar
                wire:model.live="activityPreviewTab"
                label-div-class="flex gap-5 overflow-x-auto px-1 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="relative flex min-h-0 w-full flex-1 flex-col"
                toolbar-wrapper-class="hidden"
                data-ui="overlay-sticky-tabs"
            >
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="event-activity-preview-tab-info" icon="o-light-bulb">
                    @include('livewire.events.partials.activity-preview-info-tab', [
                        'activity' => $previewActivity,
                        'previewAbout' => $previewAbout,
                        'badgeItems' => $previewActivityBadgeItems,
                    ])
                </x-tab>

                @if ($showPreviewParticipationTab ?? false)
                    <x-tab name="participation" :label="$previewParticipationLabel" class="!p-0" data-ui="event-activity-preview-tab-participation" icon="o-users">
                        @include('livewire.events.partials.activity-preview-participation-tab', [
                            'activity' => $previewActivity,
                            'participation' => $previewActivityParticipation,
                        ])
                    </x-tab>
                @endif
            </x-ui.tabs-with-toolbar>

        </div>

        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2" data-ui="event-activity-preview-actions">
                @php
                    $previewActivitySharePayload = app(\App\Support\Sharing\ShareLinks::class)->forActivity($previewActivity);
                    $previewActivityCalendarPayload = app(\App\Support\Calendar\CalendarLinks::class)->forActivity($previewActivity);
                @endphp
                @if ($previewActivitySharePayload)
                    <x-ui.share-menu :payload="$previewActivitySharePayload" open-upward />
                @endif
                @if ($previewActivityCalendarPayload)
                    <x-ui.calendar-menu :payload="$previewActivityCalendarPayload" open-upward />
                @endif
                @auth
                    @if ($showPreviewParticipationTab ?? false)
                        @if ($previewActivityParticipation?->isParticipant)
                            <x-button type="button" class="btn-error" wire:click="leavePreviewActivity" spinner="leavePreviewActivity">
                                {{ __('ui.activities.leave') }}
                            </x-button>
                        @elseif ($previewActivityParticipation?->onWaitlist)
                            <x-button type="button" class="btn-neutral" wire:click="leavePreviewWaitlist" spinner="leavePreviewWaitlist">
                                {{ __('ui.activities.leave_waitlist') }}
                            </x-button>
                        @elseif ($previewCanJoinWaitlist)
                            <x-button type="button" class="btn-primary" wire:click="joinPreviewWaitlist" spinner="joinPreviewWaitlist">
                                {{ __('ui.activities.join_waitlist') }}
                            </x-button>
                        @elseif ($previewCanJoinDirectly)
                            <x-button type="button" class="btn-primary" wire:click="joinPreviewActivity" spinner="joinPreviewActivity">
                                {{ __('ui.activities.join') }}
                            </x-button>
                        @else
                            <x-button type="button" class="btn-primary" disabled>
                                {{ __('ui.activities.join') }}
                            </x-button>
                        @endif
                    @endif
                @else
                    @if ($showPreviewParticipationTab ?? false)
                        @if ($previewCanPromptGuestJoinWaitlist)
                            <x-button
                                :link="$previewGuestLoginUrl"
                                :no-wire-navigate="true"
                                class="btn-primary"
                                data-ui="event-activity-preview-guest-join-waitlist"
                            >
                                {{ __('ui.activities.join_waitlist') }}
                            </x-button>
                        @elseif ($previewCanPromptGuestJoinDirectly)
                            <x-button
                                :link="$previewGuestLoginUrl"
                                :no-wire-navigate="true"
                                class="btn-primary"
                                data-ui="event-activity-preview-guest-join"
                            >
                                {{ __('ui.activities.join') }}
                            </x-button>
                        @endif
                    @endif
                @endauth

                @if ($previewActivityShowDetailsLink ?? false)
                    <x-button
                        :link="route('activities.show', $previewActivity)"
                        class="btn-outline"
                        wire:navigate
                    >
                        {{ __('ui.activities.show_details') }}
                    </x-button>
                @elseif ($previewActivityIsOwner ?? false)
                    <x-button
                        :link="$previewActivityEditUrl"
                        class="btn-outline"
                        wire:navigate
                    >
                        {{ __('ui.activities.edit_activity') }}
                    </x-button>
                @endif
            </div>
        </x-slot:actions>
    </x-modal>
@endif
