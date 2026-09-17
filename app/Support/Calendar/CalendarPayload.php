<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Carbon\CarbonInterface;

final readonly class CalendarPayload
{
    public function __construct(
        public string $uid,
        public string $title,
        public string $description,
        public string $location,
        public string $url,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public string $downloadFilename,
        public string $icsDownloadUrl,
    ) {}
}
