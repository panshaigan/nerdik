<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EventEmptySlotCloneService
{
    /**
     * Recreate slots on {@see $target} from {@see $source}: same relative schedule, place, capacity,
     * approval flag, and allowed activity types; never copies {@see Slot::$activity_id}.
     *
     * Slot datetimes are shifted by the same offset as {@see Event::$starts_at} between source and target
     * so duplicated editions keep their slot layout aligned with the new event window.
     */
    public function cloneEmptySlots(Event $source, Event $target): void
    {
        $source->loadMissing([
            'slots' => fn ($q) => $q->orderBy('id'),
        ]);

        $offsetSeconds = 0;
        if ($source->starts_at !== null && $target->starts_at !== null) {
            $offsetSeconds = $target->starts_at->getTimestamp() - $source->starts_at->getTimestamp();
        }

        foreach ($source->slots as $slot) {
            // `Slot` exposes allowed types via the `activity_types` accessor (array of ids).
            // The `activityTypes` *property* resolves to that accessor, not the relationship collection.
            $slot->loadMissing('activityTypes');
            $typeIds = $slot->activity_types_ids;

            $new = $target->slots()->create([
                'name' => $slot->name,
                'starts_at' => $this->shiftDateTime($slot->starts_at, $offsetSeconds),
                'ends_at' => $this->shiftDateTime($slot->ends_at, $offsetSeconds),
                'requires_approval' => $slot->requires_approval,
                'max_capacity' => $slot->max_capacity,
                'place_id' => $slot->place_id,
                'activity_id' => null,
            ]);

            if ($typeIds !== []) {
                $new->setActivityTypes($typeIds);
            }
        }
    }

    private function shiftDateTime(?CarbonInterface $value, int $offsetSeconds): ?CarbonInterface
    {
        if ($value === null || $offsetSeconds === 0) {
            return $value;
        }

        return Carbon::parse($value)->addSeconds($offsetSeconds);
    }
}
