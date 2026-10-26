<?php

namespace Tests\Unit\Services;

use App\Enums\LotteryDrawTrigger;
use App\Models\EventEnrollmentWindow;
use App\Services\LotteryDrawSchedule;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LotteryDrawScheduleTest extends TestCase
{
    #[Test]
    public function enrollment_window_id_is_null_without_window(): void
    {
        $schedule = new LotteryDrawSchedule(
            Carbon::parse('2026-06-01 12:00:00'),
            LotteryDrawTrigger::Final,
        );

        $this->assertNull($schedule->enrollmentWindowId());
    }

    #[Test]
    public function enrollment_window_id_returns_window_primary_key(): void
    {
        $window = new EventEnrollmentWindow;
        $window->id = 42;

        $schedule = new LotteryDrawSchedule(
            Carbon::parse('2026-06-01 12:00:00'),
            LotteryDrawTrigger::EnrollmentWindowEnd,
            $window,
        );

        $this->assertSame(42, $schedule->enrollmentWindowId());
    }
}
