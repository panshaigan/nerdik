<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Events;

use App\Support\Events\EventEditionDateBumper;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventEditionDateBumperTest extends TestCase
{
    private EventEditionDateBumper $bumper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bumper = new EventEditionDateBumper;
    }

    #[Test]
    public function it_preserves_wall_clock_time_in_display_timezone(): void
    {
        $original = Carbon::parse('2026-06-15 17:00:00', 'UTC'); // 19:00 Warsaw (CEST)
        $bumped = $this->bumper->bump($original);

        $this->assertSame(19, $bumped->hour);
        $this->assertSame(0, $bumped->minute);
        $this->assertSame($original->timezone(display_timezone())->dayOfWeek, $bumped->dayOfWeek);
    }

    #[Test]
    public function it_keeps_the_same_weekday_after_month_bump(): void
    {
        // Friday 2026-05-15 18:30 Warsaw
        $original = Carbon::parse('2026-05-15 16:30:00', 'UTC');
        $this->assertSame(Carbon::FRIDAY, $original->timezone(display_timezone())->dayOfWeek);

        $bumped = $this->bumper->bump($original);

        $this->assertSame(Carbon::FRIDAY, $bumped->dayOfWeek);
        $this->assertSame(18, $bumped->hour);
        $this->assertSame(30, $bumped->minute);
    }

    #[Test]
    public function it_formats_datetime_local_in_display_timezone(): void
    {
        $original = Carbon::parse('2026-06-15 17:00:00', 'UTC');
        $formatted = $this->bumper->formatForDatetimeLocal($original);
        $expected = $this->bumper->bump($original)->format('Y-m-d\TH:i');

        $this->assertSame($expected, $formatted);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $formatted);
    }
}
