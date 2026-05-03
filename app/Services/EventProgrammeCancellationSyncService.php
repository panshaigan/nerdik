<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;

class EventProgrammeCancellationSyncService
{
    /**
     * Cancel all activities currently on this event's programme slots.
     * Skips sessions already cancelled; does not notify (event-level notification covers stakeholders).
     */
    public function cancelScheduledActivitiesForEvent(Event $event, User $actor, ?string $reason): void
    {
        $activityIds = $this->distinctScheduledActivityIds($event);

        if ($activityIds === []) {
            return;
        }

        Activity::query()
            ->whereIn('id', $activityIds)
            ->whereNull('cancelled_at')
            ->update([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason !== null ? trim($reason) : null,
                'cancelled_with_event_id' => $event->getKey(),
            ]);
    }

    /**
     * Reopen activities that were cancelled solely because this event was cancelled.
     */
    public function reopenActivitiesCancelledWithEvent(Event $event): void
    {
        Activity::query()
            ->where('cancelled_with_event_id', $event->getKey())
            ->update([
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
                'cancelled_with_event_id' => null,
            ]);
    }

    /**
     * @return list<int>
     */
    private function distinctScheduledActivityIds(Event $event): array
    {
        return Slot::query()
            ->where('event_id', $event->getKey())
            ->whereNotNull('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
