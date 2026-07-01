<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Support\Collection;

final class ActivityShowSchedulePresenter
{
    public function build(Activity $activity): ActivityShowScheduleViewData
    {
        $activity->loadMissing([
            'slot.event.places.city',
            'slot.place.parent.city',
            'slot.place.city',
            'place.parent.city',
            'place.city',
        ]);

        $selfHosted = (int) $activity->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED;
        $slot = $activity->slot;
        $event = $slot?->event;

        $schedulePlace = $selfHosted
            ? $activity->place
            : ($slot?->place ?? $activity->place);

        $scheduleVenue = $schedulePlace?->parent ?? $schedulePlace;
        $scheduleRoom = $schedulePlace?->parent ? $schedulePlace->name : null;
        $eventVenues = $this->eventVenuesWithCoordinates($event);
        $schedulePlaceSummary = null;

        if ($scheduleVenue === null && $event !== null) {
            if ($eventVenues->count() === 1) {
                $scheduleVenue = $eventVenues->first();
            } elseif ($event->places->isNotEmpty()) {
                $schedulePlaceSummary = $event->compactPlaceSummary();
            }
        }

        $scheduleStartsAt = $selfHosted ? $activity->starts_at : $slot?->starts_at;
        $scheduleEndsAt = $selfHosted ? $activity->ends_at : $slot?->ends_at;
        $scheduleDateSummary = $scheduleStartsAt && $scheduleEndsAt
            ? format_datetime_range_compact($scheduleStartsAt, $scheduleEndsAt)
            : ($scheduleStartsAt ? format_in_user_tz($scheduleStartsAt, 'D, M j · H:i') : null);

        return new ActivityShowScheduleViewData(
            scheduleVenue: $scheduleVenue,
            scheduleRoom: $scheduleRoom,
            schedulePlaceSummary: $schedulePlaceSummary,
            scheduleDateSummary: $scheduleDateSummary,
            scheduleMapConfig: [
                'places' => $this->buildMapPlaces($scheduleVenue, $eventVenues),
            ],
        );
    }

    /**
     * @return Collection<int, Place>
     */
    private function eventVenuesWithCoordinates(?Event $event): Collection
    {
        if ($event === null) {
            return collect();
        }

        return $event->places
            ->filter(fn (?Place $place): bool => $place !== null
                && $place->type === Place::TYPE_VENUE
                && $place->latitude !== null
                && $place->longitude !== null)
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, Place>  $eventVenues
     * @return list<array{name: string, lat: float, lng: float}>
     */
    private function buildMapPlaces(?Place $scheduleVenue, Collection $eventVenues): array
    {
        if ($scheduleVenue !== null
            && $scheduleVenue->latitude !== null
            && $scheduleVenue->longitude !== null) {
            return [[
                'name' => (string) $scheduleVenue->name,
                'lat' => (float) $scheduleVenue->latitude,
                'lng' => (float) $scheduleVenue->longitude,
            ]];
        }

        return $eventVenues
            ->map(fn (Place $place): array => [
                'name' => (string) $place->name,
                'lat' => (float) $place->latitude,
                'lng' => (float) $place->longitude,
            ])
            ->all();
    }
}
