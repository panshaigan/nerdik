<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Event;
use App\Support\Ui\BrowseListingCardPresenter;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Database\Eloquent\Builder;

trait WithEventPreviewModal
{
    public bool $eventPreviewModalOpen = false;

    public ?int $previewEventId = null;

    public function openListingEventPreview(int $eventId): void
    {
        $this->openEventPreview($eventId);
    }

    public function openEventPreview(int $eventId): void
    {
        $event = $this->previewEventQuery($eventId)->firstOrFail();

        $this->previewEventId = (int) $event->id;
        $this->eventPreviewModalOpen = true;
    }

    public function closeEventPreview(): void
    {
        $this->eventPreviewModalOpen = false;
        $this->previewEventId = null;
    }

    public function updatedEventPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->closeEventPreview();
        }
    }

    /**
     * @return array{
     *     previewEvent: ?Event,
     *     previewEventBadgeItems: array<int, mixed>,
     *     previewEventTimeSummary: string,
     *     previewEventLocationSummary: string,
     *     previewEventCoverPicture: ListingCardPicture,
     * }
     */
    protected function resolveEventPreviewViewData(BrowseListingCardPresenter $presenter): array
    {
        $previewEvent = $this->eventPreviewModalOpen && $this->previewEventId !== null
            ? $this->previewEventQuery($this->previewEventId)->first()
            : null;

        if ($previewEvent === null && $this->eventPreviewModalOpen) {
            $this->closeEventPreview();

            return [
                'previewEvent' => null,
                'previewEventBadgeItems' => [],
                'previewEventTimeSummary' => '',
                'previewEventLocationSummary' => '',
                'previewEventCoverPicture' => ListingCardPicture::globalFallback(),
            ];
        }

        if ($previewEvent === null) {
            return [
                'previewEvent' => null,
                'previewEventBadgeItems' => [],
                'previewEventTimeSummary' => '',
                'previewEventLocationSummary' => '',
                'previewEventCoverPicture' => ListingCardPicture::globalFallback(),
            ];
        }

        $cardViewData = $presenter->fromEvent($previewEvent, []);

        return [
            'previewEvent' => $previewEvent,
            'previewEventBadgeItems' => $cardViewData->badgeItems,
            'previewEventTimeSummary' => $cardViewData->timeSummary,
            'previewEventLocationSummary' => $cardViewData->locationSummary,
            'previewEventCoverPicture' => $cardViewData->coverPicture,
        ];
    }

    protected function previewEventQuery(int $eventId): Builder
    {
        return Event::query()
            ->whereKey($eventId)
            ->with([
                'creator',
                'canceller',
                'organization',
                'places.country.translations',
                'places.city.translations',
                'slots.activity.activityType',
                'slots.activityTypes',
            ]);
    }
}
