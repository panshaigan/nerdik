<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\EventCancelledNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class CancellationNotificationDispatcher
{
    public function notifyActivityCancelled(Activity $activity, User $cancelledBy): void
    {
        $activity->loadMissing(['creator', 'slot.event']);

        $recipientIds = collect($this->activityParticipantUserIds($activity))
            ->merge($this->activityWaitlistUserIds($activity));

        $hostId = $activity->created_by !== null ? (int) $activity->created_by : null;
        if ($hostId !== null && (int) $cancelledBy->id !== $hostId) {
            $recipientIds->push($hostId);
        }

        /** @var list<int> $uniqueRecipients */
        $uniqueRecipients = $recipientIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->reject(fn (int $id): bool => $id === (int) $cancelledBy->id)
            ->values()
            ->all();

        if ($uniqueRecipients === []) {
            return;
        }

        Notification::send(
            User::query()->whereKey($uniqueRecipients)->get(),
            new ActivityCancelledNotification($activity, $cancelledBy)
        );
    }

    public function notifyEventCancelled(Event $event, User $cancelledBy): void
    {
        $recipientIds = $this->eventCancellationRecipientIds($event, $cancelledBy);

        if ($recipientIds === []) {
            return;
        }

        $notification = new EventCancelledNotification(
            $event->getKey(),
            (string) $event->name,
            (string) $event->slug
        );

        Notification::send(User::query()->whereKey($recipientIds)->get(), $notification);
    }

    /** @return list<int> */
    private function activityParticipantUserIds(Activity $activity): array
    {
        return ActivityUser::query()
            ->where('activity_id', $activity->getKey())
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id <= 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function activityWaitlistUserIds(Activity $activity): array
    {
        return ActivityWaitlistEntry::query()
            ->where('activity_id', $activity->getKey())
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id <= 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function eventCancellationRecipientIds(Event $event, User $cancelledBy): array
    {
        /** @var Collection<int> $ids */
        $ids = collect();

        /** @var list<int|string> $activityIds */
        $activityIds = Slot::query()
            ->where('event_id', $event->getKey())
            ->whereNotNull('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($activityIds !== []) {
            $participantIds = ActivityUser::query()
                ->whereIn('activity_id', $activityIds)
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);

            $hostIds = Activity::query()
                ->whereIn('id', $activityIds)
                ->whereNotNull('created_by')
                ->pluck('created_by')
                ->map(fn ($id) => (int) $id);

            $waitlistIds = ActivityWaitlistEntry::query()
                ->whereIn('activity_id', $activityIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);

            $ids = $ids->merge($participantIds)->merge($hostIds)->merge($waitlistIds);
        }

        $proposalCreatorIds = ActivityProposal::query()
            ->where('event_id', $event->getKey())
            ->where('status', ActivityProposalStatus::Pending)
            ->pluck('created_by')
            ->map(fn ($id) => (int) $id);

        $ids = $ids->merge($proposalCreatorIds);

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->reject(fn (int $id): bool => $id === (int) $cancelledBy->id)
            ->values()
            ->all();
    }
}
