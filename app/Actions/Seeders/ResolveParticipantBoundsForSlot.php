<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Activity;
use App\Models\Slot;

use function fake;

final class ResolveParticipantBoundsForSlot
{
    /**
     * @return array{min_participants: int, max_participants: int}
     */
    public function __invoke(Slot $slot, Activity $activity): array
    {
        if ($slot->max_capacity === null) {
            $maxParticipants = fake()->numberBetween(3, 8);
            $minParticipants = fake()->numberBetween(1, min(3, $maxParticipants));

            return [
                'min_participants' => $minParticipants,
                'max_participants' => $maxParticipants,
            ];
        }

        $capacity = (int) $slot->max_capacity;
        $maxParticipants = $activity->is_host_passive
            ? $capacity
            : max(0, $capacity - 1);
        $minParticipants = $maxParticipants === 0
            ? 0
            : fake()->numberBetween(1, min(3, $maxParticipants));

        return [
            'min_participants' => $minParticipants,
            'max_participants' => $maxParticipants,
        ];
    }
}
