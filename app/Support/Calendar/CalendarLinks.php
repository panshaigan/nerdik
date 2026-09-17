<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Models\Activity;
use App\Models\Event;
use App\Support\Ui\ActivityShowSchedulePresenter;
use App\Support\Ui\ActivityShowScheduleViewData;
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

        $title = (string) $event->name;
        $description = $this->descriptionFromRichText($event->description, $title);
        $location = $event->compactPlaceSummary();
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

        $title = (string) $activity->name;
        $description = $this->descriptionFromRichText($activity->description, $title);
        $location = $this->activityLocation($schedule);
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
            'location' => $payload->location,
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
            'location' => $payload->location,
            'allday' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function descriptionFromRichText(?string $stored, string $title): string
    {
        $excerpt = rich_text_excerpt($stored, 500);

        if ($excerpt === '') {
            return (string) __('ui.seo.entity_fallback_description', ['name' => $title]);
        }

        return $excerpt;
    }

    private function activityLocation(ActivityShowScheduleViewData $schedule): string
    {
        if (filled($schedule->schedulePlaceSummary)) {
            return (string) $schedule->schedulePlaceSummary;
        }

        $parts = array_values(array_filter([
            $schedule->scheduleVenue?->name,
            $schedule->scheduleRoom,
        ], fn (?string $part): bool => filled($part)));

        return implode(' · ', $parts);
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
