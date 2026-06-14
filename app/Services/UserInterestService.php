<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\User;

class UserInterestService
{
    public function __construct(
        private readonly EventShowReadCache $eventShowReadCache,
    ) {}

    public function addEventInterest(User $user, Event $event): void
    {
        $user->interestedEvents()->syncWithoutDetaching([$event->id]);
        $this->eventShowReadCache->forgetEventInterestedCount((int) $event->id);
    }

    public function removeEventInterest(User $user, Event $event): void
    {
        $user->interestedEvents()->detach($event->id);
        $this->eventShowReadCache->forgetEventInterestedCount((int) $event->id);
    }

    public function addActivityInterest(User $user, Activity $activity): void
    {
        $user->interestedActivities()->syncWithoutDetaching([$activity->id]);

        $eventId = $this->hostedEventId($activity);
        if ($eventId !== null) {
            $this->addEventInterest($user, Event::query()->whereKey($eventId)->firstOrFail());
        }
    }

    public function removeActivityInterest(User $user, Activity $activity): void
    {
        $user->interestedActivities()->detach($activity->id);
    }

    /**
     * @return bool True when interest was added, false when removed.
     */
    public function toggleEventInterest(User $user, Event $event): bool
    {
        $alreadyInterested = $user->interestedEvents()->whereKey($event->id)->exists();
        if ($alreadyInterested) {
            $this->removeEventInterest($user, $event);

            return false;
        }

        $this->addEventInterest($user, $event);

        return true;
    }

    /**
     * @return bool True when interest was added, false when removed.
     */
    public function toggleActivityInterest(User $user, Activity $activity): bool
    {
        $alreadyInterested = $user->interestedActivities()->whereKey($activity->id)->exists();
        if ($alreadyInterested) {
            $this->removeActivityInterest($user, $activity);

            return false;
        }

        $this->addActivityInterest($user, $activity);

        return true;
    }

    private function hostedEventId(Activity $activity): ?int
    {
        $eventId = $activity->slot?->event_id;

        return $eventId !== null ? (int) $eventId : null;
    }
}
