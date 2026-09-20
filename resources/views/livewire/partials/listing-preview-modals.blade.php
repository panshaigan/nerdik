@if ($includeActivityPreviewModal ?? true)
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
@endif

@if ($includeEventPreviewModal ?? false)
    @include('livewire.partials.listing-event-preview-modal', [
        'previewEvent' => $previewEvent ?? null,
        'previewEventBadgeItems' => $previewEventBadgeItems ?? [],
        'previewEventTimeSummary' => $previewEventTimeSummary ?? '',
        'previewEventLocationSummary' => $previewEventLocationSummary ?? '',
        'previewEventLocationPlaces' => $previewEventLocationPlaces ?? [],
        'previewEventCoverPicture' => $previewEventCoverPicture ?? \App\Support\Ui\ListingCardPicture::empty(),
    ])
@endif
