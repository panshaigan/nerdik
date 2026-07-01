<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Models\Place;

final readonly class ActivityShowScheduleViewData
{
    /**
     * @param  array{places: list<array{name: string, lat: float, lng: float}>}  $scheduleMapConfig
     */
    public function __construct(
        public ?Place $scheduleVenue,
        public ?string $scheduleRoom,
        public ?string $schedulePlaceSummary,
        public ?string $scheduleDateSummary,
        public array $scheduleMapConfig,
    ) {}
}
