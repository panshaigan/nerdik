@if ($includeActivityPreviewModal ?? true)
    @include('livewire.events.partials.activity-preview-modal', [
        'previewActivity' => $previewActivity ?? null,
        'previewAbout' => $previewAbout ?? null,
        'previewActivityBadgeItems' => $previewActivityBadgeItems ?? [],
        'previewActivityParticipation' => $previewActivityParticipation ?? null,
        'previewActivityHasActiveEnrollmentWindow' => $previewActivityHasActiveEnrollmentWindow ?? false,
        'showPreviewParticipationActions' => $showPreviewParticipationActions ?? false,
        'activityPreviewRefreshTick' => $activityPreviewRefreshTick ?? 0,
    ])
@endif

@if ($includeEventPreviewModal ?? false)
    @include('livewire.partials.listing-event-preview-modal', [
        'previewEvent' => $previewEvent ?? null,
        'previewEventBadgeItems' => $previewEventBadgeItems ?? [],
        'previewEventTimeSummary' => $previewEventTimeSummary ?? '',
        'previewEventLocationSummary' => $previewEventLocationSummary ?? '',
        'previewEventCoverPicture' => $previewEventCoverPicture ?? \App\Support\Ui\ListingCardPicture::globalFallback(),
    ])
@endif
