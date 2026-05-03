<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityHostingModeService
{
    public function setDraft(Activity $activity): void
    {
        $this->ensureNotScheduledOnEvent($activity);
        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function setSelfHosted(Activity $activity, int $placeId, string $startsAt): void
    {
        $this->ensureNotScheduledOnEvent($activity);

        $place = Place::query()->find($placeId);
        if ($place === null) {
            throw ValidationException::withMessages([
                'self_hosted_place_id' => [__('validation.exists', ['attribute' => 'self_hosted_place_id'])],
            ]);
        }

        $start = parse_datetime_to_utc($startsAt);
        if ($start === null) {
            throw ValidationException::withMessages([
                'self_hosted_starts_at' => [__('validation.date', ['attribute' => 'self_hosted_starts_at'])],
            ]);
        }

        $end = $this->deriveEndsAt($activity, $start);

        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end?->toDateTimeString(),
        ]);
    }

    public function moveSelfHostedToProposed(Activity $activity): void
    {
        $this->ensureNotScheduledOnEvent($activity);

        if ($activity->participants()->exists() || $activity->waitlist()->exists()) {
            throw ValidationException::withMessages([
                'hosting_mode' => [__('ui.activities.cannot_propose_self_hosted_with_participants')],
            ]);
        }

        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function markProposedToEvent(Activity $activity): void
    {
        $this->ensureNotScheduledOnEvent($activity);
        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function markScheduledOnEvent(Activity $activity): void
    {
        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function markRejectedProposal(Activity $activity): void
    {
        $this->ensureNotScheduledOnEvent($activity);
        $activity->update([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function cancel(Activity $activity, User $actor, ?string $reason = null): void
    {
        $activity->update([
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancel_reason' => $reason !== null ? trim($reason) : null,
        ]);

        app(CancellationNotificationDispatcher::class)->notifyActivityCancelled(
            $activity->fresh(),
            $actor
        );
    }

    public function reopen(Activity $activity, User $actor): void
    {
        $activity->update([
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'cancelled_with_event_id' => null,
        ]);

        app(CancellationNotificationDispatcher::class)->notifyActivityReopened(
            $activity->fresh(),
            $actor
        );
    }

    public function detachAcceptedSlot(Event $event, Slot $slot): bool
    {
        if ($slot->activity_id === null) {
            return false;
        }

        DB::transaction(function () use ($slot, $event): void {
            $activityId = (int) $slot->activity_id;

            $proposal = ActivityProposal::query()
                ->where('event_id', $event->id)
                ->where('activity_id', $activityId)
                ->where('accepted_slot_id', $slot->id)
                ->first();

            if ($proposal === null) {
                $proposal = ActivityProposal::query()
                    ->where('event_id', $event->id)
                    ->where('activity_id', $activityId)
                    ->where('status', ActivityProposalStatus::Accepted)
                    ->first();
            }

            $slot->update(['activity_id' => null]);

            if ($proposal !== null) {
                $proposal->update([
                    'status' => ActivityProposalStatus::Pending,
                    'accepted_slot_id' => null,
                ]);
            }

            $activity = Activity::query()->find($activityId);
            if ($activity !== null) {
                $activity->update([
                    'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
                    'place_id' => null,
                    'starts_at' => null,
                    'ends_at' => null,
                ]);
            }
        });

        return true;
    }

    private function ensureNotScheduledOnEvent(Activity $activity): void
    {
        if ($activity->hosting_mode === Activity::HOSTING_MODE_SCHEDULED_ON_EVENT) {
            throw ValidationException::withMessages([
                'hosting_mode' => [__('ui.activities.hosting_mode_locked_scheduled')],
            ]);
        }
    }

    private function deriveEndsAt(Activity $activity, Carbon $start): ?Carbon
    {
        $minutes = (int) ($activity->duration_in_minutes ?? 0);
        if ($minutes <= 0) {
            return null;
        }

        return $start->copy()->addMinutes($minutes);
    }
}
