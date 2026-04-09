<?php

namespace App\Services;

use App\Models\Event;

class EventEmptySlotCloneService
{
    /**
     * Recreate slots on {@see $target} from {@see $source}: same schedule, place, capacity, approval flag,
     * and allowed activity types; never copies {@see Slot::$activity_id}.
     */
    public function cloneEmptySlots(Event $source, Event $target): void
    {
        $source->loadMissing([
            'slots' => fn ($q) => $q->orderBy('id'),
        ]);

        foreach ($source->slots as $slot) {
            // `Slot` exposes allowed types via the `activity_types` accessor (array of ids).
            // The `activityTypes` *property* resolves to that accessor, not the relationship collection.
            $slot->loadMissing('activityTypes');
            $typeIds = $slot->activity_types;

            $new = $target->slots()->create([
                'name' => $slot->name,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
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
}
