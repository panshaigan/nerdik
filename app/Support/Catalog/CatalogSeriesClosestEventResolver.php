<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Event;
use App\Models\EventSeries;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Support\Collection;

final class CatalogSeriesClosestEventResolver
{
    public function __construct(
        private EventListingImageResolver $eventListingImageResolver,
    ) {}

    /**
     * @param  Collection<int, EventSeries>  $seriesList
     * @return array<int, ListingCardPicture>
     */
    public function coverPicturesBySeriesId(Collection $seriesList): array
    {
        $seriesIds = $seriesList->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($seriesIds === []) {
            return [];
        }

        /** @var Collection<int, Collection<int, Event>> $eventsBySeriesId */
        $eventsBySeriesId = Event::query()
            ->whereIn('event_series_id', $seriesIds)
            ->where('is_public', true)
            ->whereNull('cancelled_at')
            ->with(['listingMedia', 'galleryMedia', 'media' => fn ($query) => $query->where('collection_name', 'logo')])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Event $event): int => (int) $event->event_series_id);

        $now = now();
        $covers = [];

        foreach ($seriesIds as $seriesId) {
            /** @var Collection<int, Event> $events */
            $events = $eventsBySeriesId->get($seriesId, collect());
            $closest = $events
                ->filter(fn (Event $event): bool => $event->starts_at !== null && $event->starts_at->gte($now))
                ->sortBy([
                    ['starts_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();

            if ($closest === null) {
                $closest = $events
                    ->sortBy([
                        ['starts_at', 'desc'],
                        ['id', 'desc'],
                    ])
                    ->first();
            }

            if ($closest === null) {
                continue;
            }

            $picture = $this->eventListingImageResolver->resolve($closest);
            if ($picture->hasDisplayableImage()) {
                $covers[$seriesId] = $picture;
            }
        }

        return $covers;
    }
}
