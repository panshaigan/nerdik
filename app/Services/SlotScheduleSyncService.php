<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Slot;

/**
 * Keeps slot {@see Slot::$ends_at} aligned with attached activity duration (start + duration).
 */
class SlotScheduleSyncService
{
    /**
     * For each slot on the event that has a timed activity with a positive duration, set ends_at to start + duration.
     */
    public function syncSlotEndsForEvent(Event $event): void
    {
        foreach ($event->slots as $slot) {
            $activity = $slot->activity;
            if ($activity === null || ! $slot->starts_at) {
                continue;
            }
            $minutes = (int) ($activity->duration_in_minutes ?? 0);
            if ($minutes <= 0) {
                continue;
            }
            $expected = $slot->starts_at->copy()->addMinutes($minutes);
            if ($slot->ends_at === null || ! $slot->ends_at->equalTo($expected)) {
                $slot->ends_at = $expected;
                $slot->save();
            }
        }
    }
}
