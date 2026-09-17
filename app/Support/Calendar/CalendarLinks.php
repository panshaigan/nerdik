<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Support\Ui\ActivityShowSchedulePresenter;
use App\Support\Ui\ActivityShowScheduleViewData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CalendarLinks
{
    public function __construct(
        private readonly ActivityShowSchedulePresenter $activitySchedulePresenter,
        private readonly IcsGenerator $icsGenerator,
    ) {}

    public function forEvent(Event $event): ?CalendarPayload
    {
        if ($event->isCancelled() || $event->starts_at === null) {
            return null;
        }

        $startsAt = $event->starts_at;
        $endsAt = $event->ends_at ?? $startsAt;
        if ($endsAt->lt($startsAt)) {
            $endsAt = $startsAt;
        }

        $event->loadMissing('places.city');
        $venues = $this->eventVenues($event);
        [$location, $latitude, $longitude] = $this->locationFromVenues($venues);

        $title = (string) $event->name;
        $description = $this->descriptionFromRichText($event->description, $title);
        $url = route('events.show', $event);

        return new CalendarPayload(
            uid: $this->uid('event', (int) $event->id),
            title: $title,
            description: $description,
            location: $location,
            url: $url,
            startsAt: $startsAt,
            endsAt: $endsAt,
            downloadFilename: $this->downloadFilename($title),
            icsDownloadUrl: route('events.calendar.ics', $event),
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    public function forActivity(Activity $activity): ?CalendarPayload
    {
        if ($activity->isCancelled()) {
            return null;
        }

        $schedule = $this->activitySchedulePresenter->build($activity);
        $selfHosted = (int) $activity->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED;
        $slot = $activity->slot;

        $startsAt = $selfHosted ? $activity->starts_at : $slot?->starts_at;
        $endsAt = $selfHosted ? $activity->ends_at : $slot?->ends_at;

        if ($startsAt === null) {
            return null;
        }

        $endsAt ??= $startsAt;
        if ($endsAt->lt($startsAt)) {
            $endsAt = $startsAt;
        }

        [$location, $latitude, $longitude] = $this->activityLocationParts($schedule);

        $title = (string) $activity->name;
        $description = $this->descriptionFromRichText($activity->description, $title);
        $url = route('activities.show', $activity);

        return new CalendarPayload(
            uid: $this->uid('activity', (int) $activity->id),
            title: $title,
            description: $description,
            location: $location,
            url: $url,
            startsAt: $startsAt,
            endsAt: $endsAt,
            downloadFilename: $this->downloadFilename($title),
            icsDownloadUrl: route('activities.calendar.ics', $activity),
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    public function icsContent(CalendarPayload $payload): string
    {
        return $this->icsGenerator->generate($payload);
    }

    public function intentUrl(CalendarPayload $payload, CalendarTarget $target): ?string
    {
        return match ($target) {
            CalendarTarget::Google => $this->googleUrl($payload),
            CalendarTarget::Outlook => $this->outlookUrl($payload),
            CalendarTarget::Download => $payload->icsDownloadUrl,
        };
    }

    private function googleUrl(CalendarPayload $payload): string
    {
        $dates = $this->icsGenerator->formatUtc($payload->startsAt)
            .'/'.$this->icsGenerator->formatUtc($payload->endsAt);

        $details = $payload->description;
        if ($payload->url !== '') {
            $details = trim($details."\n\n".$payload->url);
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $payload->title,
            'dates' => $dates,
            'details' => $details,
            'location' => $this->deepLinkLocation($payload),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function outlookUrl(CalendarPayload $payload): string
    {
        $body = $payload->description;
        if ($payload->url !== '') {
            $body = trim($body."\n\n".$payload->url);
        }

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query([
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $payload->title,
            'body' => $body,
            'startdt' => $payload->startsAt->copy()->utc()->toIso8601String(),
            'enddt' => $payload->endsAt->copy()->utc()->toIso8601String(),
            'location' => $this->deepLinkLocation($payload),
            'allday' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Deep links have no GEO field — append coordinates when LOCATION has no street address cue.
     */
    private function deepLinkLocation(CalendarPayload $payload): string
    {
        $location = $payload->location;
        if (! $payload->hasCoordinates()) {
            return $location;
        }

        $coords = sprintf('%s, %s', $payload->latitude, $payload->longitude);
        if ($location === '') {
            return $coords;
        }

        if (str_contains($location, $coords)) {
            return $location;
        }

        return $location.', '.$coords;
    }

    private function descriptionFromRichText(?string $stored, string $title): string
    {
        $excerpt = rich_text_excerpt($stored, 500);

        if ($excerpt === '') {
            return (string) __('ui.seo.entity_fallback_description', ['name' => $title]);
        }

        return $excerpt;
    }

    /**
     * @return array{0: string, 1: ?float, 2: ?float}
     */
    private function activityLocationParts(ActivityShowScheduleViewData $schedule): array
    {
        if ($schedule->scheduleVenue !== null) {
            return $this->locationFromVenues(collect([$schedule->scheduleVenue]));
        }

        if (filled($schedule->schedulePlaceSummary)) {
            return [(string) $schedule->schedulePlaceSummary, null, null];
        }

        return ['', null, null];
    }

    /**
     * @param  Collection<int, Place>  $venues
     * @return array{0: string, 1: ?float, 2: ?float}
     */
    private function locationFromVenues(Collection $venues): array
    {
        $venues = $venues
            ->filter(fn (?Place $place): bool => $place !== null && filled($place->name))
            ->unique('id')
            ->values();

        if ($venues->isEmpty()) {
            return ['', null, null];
        }

        $location = $venues
            ->map(fn (Place $place): string => $place->calendarLocationLabel())
            ->filter()
            ->implode('; ');

        if ($venues->count() === 1) {
            $coords = $venues->first()?->venueCoordinates();

            return [$location, $coords[0] ?? null, $coords[1] ?? null];
        }

        return [$location, null, null];
    }

    /**
     * @return Collection<int, Place>
     */
    private function eventVenues(Event $event): Collection
    {
        return $event->places
            ->filter(fn (?Place $place): bool => $place !== null && $place->type === Place::TYPE_VENUE)
            ->unique('id')
            ->values();
    }

    private function uid(string $type, int $id): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $host = 'nerdik.local';
        }

        return sprintf('%s-%d@%s', $type, $id, $host);
    }

    private function downloadFilename(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'calendar';
        }

        return $slug.'.ics';
    }
}
