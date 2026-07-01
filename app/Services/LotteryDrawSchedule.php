<?php

namespace App\Services;

use App\Enums\LotteryDrawTrigger;
use App\Models\EventEnrollmentWindow;
use Carbon\Carbon;

readonly class LotteryDrawSchedule
{
    public function __construct(
        public Carbon $at,
        public LotteryDrawTrigger $trigger,
        public ?EventEnrollmentWindow $enrollmentWindow = null,
    ) {}

    public function enrollmentWindowId(): ?int
    {
        return $this->enrollmentWindow?->id;
    }
}
