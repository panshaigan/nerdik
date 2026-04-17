<?php

namespace App\Services;

use App\Models\EventEnrollmentWindow;

/**
 * Active enrollment window and per-activity remaining seats for event show UI.
 */
final class EventEnrollmentPresentationData
{
    /**
     * @param  array<int, int>  $remainingByActivityId
     */
    public function __construct(
        public readonly ?EventEnrollmentWindow $activeWindow,
        public readonly array $remainingByActivityId,
    ) {}
}
