<div data-ui="event-show-activity-preview-modal">
    @include('livewire.events.partials.activity-preview-modal', [
        'previewActivity' => $previewActivity ?? null,
        'previewAbout' => $previewAbout ?? null,
        'previewActivityBadgeItems' => $previewActivityBadgeItems ?? [],
        'previewActivityParticipation' => $previewActivityParticipation ?? null,
        'previewActivityHasActiveEnrollmentWindow' => $previewActivityHasActiveEnrollmentWindow ?? false,
        'showPreviewParticipationActions' => $showPreviewParticipationActions ?? false,
        'showPreviewParticipationTab' => $showPreviewParticipationTab ?? false,
        'activityPreviewRefreshTick' => $activityPreviewRefreshTick ?? 0,
    ])

    @include('livewire.partials.familiarity-prompt-modal')
</div>
