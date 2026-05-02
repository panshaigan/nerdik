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
use App\Notifications\ActivityReopenedNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sends cancel/reopen notices to the same stakeholder sets: participants, waitlists, hosts (activity cancel parity),
 * pending proposal authors (events), and event/activity interest wishlists. The cancelling/reopening actor is excluded.
 */
class CancellationNotificationDispatcher
{
    public function notifyActivityCancelled(Activity $activity, User $cancelledBy): void
    {
        $uniqueRecipients = $this->activityStakeholderRecipientUserIds($activity, $cancelledBy);

        if ($uniqueRecipients === []) {
            return;
        }

        Notification::send(
            User::query()->whereKey($uniqueRecipients)->get(),
            new ActivityCancelledNotification($activity, $cancelledBy)
        );
    }

    /**
     * Stakeholders to notify when an activity lifecycle change affects participants, waitlist, host interest, or wishlisted users — same roster as cancellation.
     *
     * @return list<int>
     */
    public function activityStakeholderRecipientUserIds(Activity $activity, User $actor): array
    {
        $activity->loadMissing(['creator', 'slot.event']);

        $recipientIds = collect($this->activityParticipantUserIds($activity))
            ->merge($this->activityWaitlistUserIds($activity))
            ->merge($this->userIdsInterestedInActivities([(int) $activity->getKey()]));

        $hostId = $activity->created_by !== null ? (int) $activity->created_by : null;
        if ($hostId !== null && (int) $actor->id !== $hostId) {
            $recipientIds->push($hostId);
        }

        return $recipientIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->reject(fn (int $id): bool => $id === (int) $actor->id)
            ->values()
            ->all();
    }

    public function notifyActivityReopened(Activity $activity, User $reopenedBy): void
    {
        $uniqueRecipients = $this->activityStakeholderRecipientUserIds($activity, $reopenedBy);

        if ($uniqueRecipients === []) {
            return;
        }

        Notification::send(
            User::query()->whereKey($uniqueRecipients)->get(),
            new ActivityReopenedNotification($activity, $reopenedBy)
        );
    }

    public function notifyEventCancelled(Event $event, User $cancelledBy): void
    {
        $recipientIds = $this->eventStakeholderRecipientUserIds($event, $cancelledBy);

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

    /**
     * Same roster as event cancellation: programme participants/waitlists, hosts, pending proposers, event/activity interests.
     *
     * @return list<int>
     */
    public function eventStakeholderRecipientUserIds(Event $event, User $actor): array
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

        $interestUserIds = collect($this->userIdsInterestedInEvent((int) $event->getKey()))
            ->merge($this->userIdsInterestedInActivities($activityIds));

        $ids = $ids->merge($proposalCreatorIds)->merge($interestUserIds);

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->reject(fn (int $id): bool => $id === (int) $actor->id)
            ->values()
            ->all();
    }

    public function notifyEventReopened(Event $event, User $reopenedBy): void
    {
        $recipientIds = $this->eventStakeholderRecipientUserIds($event, $reopenedBy);

        if ($recipientIds === []) {
            return;
        }

        Notification::send(
            User::query()->whereKey($recipientIds)->get(),
            new EventReopenedNotification($event, $reopenedBy)
        );
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
     * @param  list<int>  $activityIds
     * @return list<int>
     */
    private function userIdsInterestedInActivities(array $activityIds): array
    {
        $normalized = [];
        foreach ($activityIds as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $normalized[$intId] = true;
            }
        }

        if ($normalized === []) {
            return [];
        }

        return DB::table('user_interests')
            ->where('interest_type', (new Activity)->getMorphClass())
            ->whereIn('interest_id', array_keys($normalized))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id <= 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function userIdsInterestedInEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        return DB::table('user_interests')
            ->where('interest_type', (new Event)->getMorphClass())
            ->where('interest_id', $eventId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id <= 0)
            ->unique()
            ->values()
            ->all();
    }
}
