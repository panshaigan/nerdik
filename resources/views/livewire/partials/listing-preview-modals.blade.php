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

    @if ($previewActivity && filled(rich_text_excerpt($previewActivity->participants_only_description)))
        @include('livewire.activities.partials.participants-info-modal', ['activity' => $previewActivity])
    @endif
@endif

@if ($includeEventPreviewModal ?? false)
    @include('livewire.partials.listing-event-preview-modal', [
        'previewEvent' => $previewEvent ?? null,
        'previewEventBadgeItems' => $previewEventBadgeItems ?? [],
        'previewEventTimeSummary' => $previewEventTimeSummary ?? '',
        'previewEventLocationSummary' => $previewEventLocationSummary ?? '',
        'previewEventLocationPlaces' => $previewEventLocationPlaces ?? [],
        'previewEventCoverPicture' => $previewEventCoverPicture ?? \App\Support\Ui\ListingCardPicture::empty(),
        'previewEventCanProposeActivity' => $previewEventCanProposeActivity ?? false,
    ])
@endif
