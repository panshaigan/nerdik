@if ($previewActivity)
    @php
        $previewParticipantCount = (int) $previewActivity->participants->count();
        $previewParticipationLabel = __('ui.activities.show_participation_section')
            .' <span class="badge badge-primary badge-sm ml-2">'
            .$previewParticipantCount.'/'.($previewActivity->max_participants ?? '∞')
            .'</span>';
        $previewCanJoinWaitlist = $previewActivityParticipation?->canJoin
            && ($previewActivity->requires_approval || $previewActivityParticipation?->isFull);
        $previewCanJoinDirectly = $previewActivityParticipation?->canJoin
            && ! $previewActivity->requires_approval
            && ! $previewActivityParticipation?->isFull;
    @endphp

    <x-modal
        wire:model="activityPreviewModalOpen"
        :title="$previewActivity->name"
        box-class="max-w-4xl bg-texture-glass"
        class="backdrop-blur"
        separator
    >
        <div
            wire:key="event-activity-preview-{{ $previewActivity->id }}-{{ $activityPreviewRefreshTick }}"
            class="space-y-5"
            data-ui="event-activity-preview-modal"
        >
            <x-ui.tabs-with-toolbar
                wire:model.live="activityPreviewTab"
                label-div-class="flex gap-5 overflow-x-auto px-1 pt-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="hidden"
                data-ui="event-activity-preview-tabs"
            >
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="event-activity-preview-tab-info" icon="o-light-bulb">
                    @include('livewire.events.partials.activity-preview-info-tab', [
                        'activity' => $previewActivity,
                        'badgeItems' => $previewActivityBadgeItems,
                    ])
                </x-tab>

                @if ($previewActivityHasActiveEnrollmentWindow)
                    <x-tab name="participation" :label="$previewParticipationLabel" class="!p-0" data-ui="event-activity-preview-tab-participation" icon="o-users">
                        @include('livewire.events.partials.activity-preview-participation-tab', [
                            'activity' => $previewActivity,
                            'participation' => $previewActivityParticipation,
                        ])
                    </x-tab>
                @endif
            </x-ui.tabs-with-toolbar>

            <div class="modal-action flex flex-wrap items-center justify-end gap-2 border-t border-base-300 pt-4" data-ui="event-activity-preview-actions">
                @auth
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
                @endauth

                <x-button
                    :link="route('activities.show', $previewActivity)"
                    class="btn-outline"
                    wire:navigate
                >
                    {{ __('ui.activities.show_details') }}
                </x-button>
            </div>
        </div>
    </x-modal>
@endif
