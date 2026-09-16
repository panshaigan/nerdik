<?php

declare(strict_types=1);

namespace App\Support\Events;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Bumps an event datetime by one calendar month for duplicate prefill,
 * preserving local wall-clock time and weekday in the display timezone.
 */
final class EventEditionDateBumper
{
    /**
     * @return Carbon Mutable instance in the display timezone
     */
    public function bump(CarbonInterface $date): Carbon
    {
        $tz = display_timezone();
        $local = Carbon::parse($date)->timezone($tz);
        $weekday = $local->dayOfWeek;
        $hour = $local->hour;
        $minute = $local->minute;
        $second = $local->second;

        $bumped = $local->copy()->addMonthNoOverflow();
        $bumped->setTime($hour, $minute, $second);

        if ($bumped->dayOfWeek !== $weekday) {
            $bumped->addDays($weekday - $bumped->dayOfWeek);
            $bumped->setTime($hour, $minute, $second);
        }

        return $bumped;
    }

    public function formatForDatetimeLocal(CarbonInterface $date): string
    {
        return $this->bump($date)->format('Y-m-d\TH:i');
    }
}
