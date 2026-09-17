<?php

declare(strict_types=1);

namespace App\Support\Sharing;

use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Support\Ui\ActivityShowSchedulePresenter;

final class ShareLinks
{
    public function __construct(
        private readonly ActivityShowSchedulePresenter $activitySchedulePresenter,
    ) {}

    public function forEvent(Event $event): ?SharePayload
    {
        if ($event->isCancelled()) {
            return null;
        }

        $title = (string) $event->name;
        $excerpt = rich_text_excerpt($event->description, 160);

        if ($excerpt === '') {
            $excerpt = (string) __('ui.seo.entity_fallback_description', ['name' => $title]);
        }

        $metaParts = array_values(array_filter([
            $event->compactDateAttribute(),
            $event->compactPlaceSummary(),
        ], fn (string $part): bool => $part !== ''));

        $text = $metaParts === []
            ? $excerpt
            : implode(' · ', $metaParts).' — '.$excerpt;

        return new SharePayload(
            url: route('events.show', $event),
            title: $title,
            text: $text,
            campaign: 'event',
        );
    }

    public function forEventSeries(EventSeries $series): SharePayload
    {
        $title = (string) $series->name;
        $excerpt = rich_text_excerpt($series->description, 160);

        if ($excerpt === '') {
            $excerpt = (string) __('ui.seo.entity_fallback_description', ['name' => $title]);
        }

        return new SharePayload(
            url: route('event-series.show', $series),
            title: $title,
            text: $excerpt,
            campaign: 'event-series',
        );
    }

    public function forActivity(Activity $activity): ?SharePayload
    {
        if ($activity->isCancelled()) {
            return null;
        }

        $title = (string) $activity->name;
        $excerpt = rich_text_excerpt($activity->description, 160);

        if ($excerpt === '') {
            $excerpt = (string) __('ui.seo.entity_fallback_description', ['name' => $title]);
        }

        $schedule = $this->activitySchedulePresenter->build($activity);
        $metaParts = array_values(array_filter([
            $schedule->scheduleDateSummary,
            $schedule->schedulePlaceSummary ?? $schedule->scheduleVenue?->name,
        ], fn (?string $part): bool => filled($part)));

        $text = $metaParts === []
            ? $excerpt
            : implode(' · ', $metaParts).' — '.$excerpt;

        return new SharePayload(
            url: route('activities.show', $activity),
            title: $title,
            text: $text,
            campaign: 'activity',
        );
    }

    public function trackedUrl(SharePayload $payload, ShareTarget $target): string
    {
        $separator = str_contains($payload->url, '?') ? '&' : '?';

        return $payload->url.$separator.http_build_query([
            'utm_source' => 'nerdik',
            'utm_medium' => 'share',
            'utm_campaign' => $payload->campaign,
            'utm_content' => $target->value,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function intentUrl(SharePayload $payload, ShareTarget $target): ?string
    {
        if (! $target->isExternal()) {
            return null;
        }

        $tracked = $this->trackedUrl($payload, $target);

        return match ($target) {
            ShareTarget::Facebook => 'https://www.facebook.com/sharer/sharer.php?'.http_build_query([
                'u' => $tracked,
            ], '', '&', PHP_QUERY_RFC3986),
            ShareTarget::WhatsApp => 'https://wa.me/?'.http_build_query([
                'text' => $this->shareMessage($payload, $tracked),
            ], '', '&', PHP_QUERY_RFC3986),
            ShareTarget::X => 'https://twitter.com/intent/tweet?'.http_build_query([
                'url' => $tracked,
                'text' => $payload->title,
            ], '', '&', PHP_QUERY_RFC3986),
            ShareTarget::Telegram => 'https://t.me/share/url?'.http_build_query([
                'url' => $tracked,
                'text' => $payload->title,
            ], '', '&', PHP_QUERY_RFC3986),
            default => null,
        };
    }

    private function shareMessage(SharePayload $payload, string $trackedUrl): string
    {
        return trim($payload->title."\n".$payload->text."\n".$trackedUrl);
    }
}
