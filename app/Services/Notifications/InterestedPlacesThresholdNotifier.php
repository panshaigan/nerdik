<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\User;
use App\Notifications\ActivityPlacesLowNotification;
use App\Notifications\EventPlacesLowNotification;
use App\Services\EventShowReadCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class InterestedPlacesThresholdNotifier
{
    public function __construct(
        private EventShowReadCache $eventShowReadCache,
    ) {}

    public function afterParticipantJoined(Activity $activity, User $excludeUser): void
    {
        $activity = $activity->fresh() ?? $activity;
        $participantCount = (int) $activity->participants()->whereNull('deleted_at')->count();
        $activityRecipientIds = $this->notifyActivityFollowersIfCrossed(
            $activity,
            $participantCount,
            $excludeUser,
        );

        $event = $activity->slot?->event;
        if (! $event instanceof Event) {
            return;
        }

        $this->notifyEventFollowersIfCrossed(
            $event,
            $excludeUser,
            $activityRecipientIds,
        );
    }

    /**
     * @return list<int>
     */
    private function notifyActivityFollowersIfCrossed(
        Activity $activity,
        int $participantCount,
        User $excludeUser,
    ): array {
        $max = $activity->max_participants;
        if ($max === null || $max <= 0) {
            return [];
        }

        if (! $this->crossedRemainingThreshold($participantCount - 1, $participantCount, (int) $max)) {
            return [];
        }

        $remaining = max(0, (int) $max - $participantCount);
        $participantIds = $this->activityParticipantUserIds((int) $activity->id);
        $excludeIds = array_values(array_unique(array_merge(
            [(int) $excludeUser->id],
            $participantIds,
        )));

        $followers = $activity->interestedUsers()
            ->whereKeyNot($excludeIds)
            ->get();

        if ($followers->isEmpty()) {
            return [];
        }

        Notification::send(
            $followers,
            new ActivityPlacesLowNotification($activity, $remaining, (int) $max),
        );

        return $followers->modelKeys();
    }

    /**
     * @param  list<int>  $excludeUserIds
     */
    private function notifyEventFollowersIfCrossed(
        Event $event,
        User $excludeUser,
        array $excludeUserIds,
    ): void {
        [, $participants, $availablePlaces] = $this->eventShowReadCache->programmeStats((int) $event->id);

        if ($availablePlaces === null || $availablePlaces <= 0) {
            return;
        }

        $previousParticipants = max(0, $participants - 1);
        if (! $this->crossedRemainingThreshold($previousParticipants, $participants, $availablePlaces)) {
            return;
        }

        $remaining = max(0, $availablePlaces - $participants);
        $excludeIds = array_values(array_unique(array_merge(
            [(int) $excludeUser->id],
            array_map('intval', $excludeUserIds),
            $this->eventProgrammeParticipantUserIds((int) $event->id),
        )));

        /** @var Collection<int, User> $followers */
        $followers = $event->interestedUsers()
            ->whereKeyNot($excludeIds)
            ->get();

        if ($followers->isEmpty()) {
            return;
        }

        Notification::send(
            $followers,
            new EventPlacesLowNotification($event, $remaining, $availablePlaces),
        );
    }

    /**
     * @return list<int>
     */
    private function activityParticipantUserIds(int $activityId): array
    {
        return ActivityUser::query()
            ->where('activity_id', $activityId)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function eventProgrammeParticipantUserIds(int $eventId): array
    {
        $programmeActivityIds = Activity::query()
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->whereNull('cancelled_at')
            ->select('activities.id');

        return ActivityUser::query()
            ->whereNull('activity_user.deleted_at')
            ->whereIn('activity_id', $programmeActivityIds)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function crossedRemainingThreshold(int $previousCount, int $currentCount, int $max): bool
    {
        if ($max <= 0) {
            return false;
        }

        $threshold = (float) config('interested_places.remaining_ratio_threshold', 0.25);

        return ! $this->isAtOrBelowThreshold($previousCount, $max, $threshold)
            && $this->isAtOrBelowThreshold($currentCount, $max, $threshold);
    }

    private function isAtOrBelowThreshold(int $count, int $max, float $threshold): bool
    {
        $remainingRatio = ($max - $count) / $max;

        return $remainingRatio <= $threshold;
    }
}
