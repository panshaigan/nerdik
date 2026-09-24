<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

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
     * Follow every given event when any is missing; otherwise unfollow all of them.
     *
     * @param  Collection<int, Event>  $events
     * @return bool True when interest was added, false when removed.
     */
    public function toggleUpcomingEventInterests(User $user, Collection $events): bool
    {
        if ($events->isEmpty()) {
            return false;
        }

        $ids = $events->pluck('id')->map(fn ($id) => (int) $id)->all();
        $interestedCount = $user->interestedEvents()->whereIn('events.id', $ids)->count();
        $shouldAdd = $interestedCount < count($ids);

        foreach ($events as $event) {
            if ($shouldAdd) {
                $this->addEventInterest($user, $event);
            } else {
                $this->removeEventInterest($user, $event);
            }
        }

        return $shouldAdd;
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

    /**
     * Follow every given activity when any is missing; otherwise unfollow all of them.
     *
     * @param  Collection<int, Activity>  $activities
     * @return bool True when interest was added, false when removed.
     */
    public function toggleUpcomingActivityInterests(User $user, Collection $activities): bool
    {
        if ($activities->isEmpty()) {
            return false;
        }

        $ids = $activities->pluck('id')->map(fn ($id) => (int) $id)->all();
        $interestedCount = $user->interestedActivities()->whereIn('activities.id', $ids)->count();
        $shouldAdd = $interestedCount < count($ids);

        foreach ($activities as $activity) {
            if ($shouldAdd) {
                $this->addActivityInterest($user, $activity);
            } else {
                $this->removeActivityInterest($user, $activity);
            }
        }

        return $shouldAdd;
    }

    private function hostedEventId(Activity $activity): ?int
    {
        $eventId = $activity->slot?->event_id;

        return $eventId !== null ? (int) $eventId : null;
    }
}
