<?php

declare(strict_types=1);

namespace App\Support\Calendar;

final readonly class CalendarSeriesPayload
{
    /**
     * @param  list<CalendarPayload>  $events
     */
    public function __construct(
        public array $events,
        public string $icsDownloadUrl,
        public string $downloadFilename,
    ) {}

    public function singleEvent(): ?CalendarPayload
    {
        return count($this->events) === 1 ? $this->events[0] : null;
    }
}
