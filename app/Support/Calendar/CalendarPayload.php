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
        public ?float $latitude = null,
        public ?float $longitude = null,
        public IcsMethod $method = IcsMethod::Publish,
        public int $sequence = 0,
    ) {}

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
