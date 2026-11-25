<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivitySlotAssignmentService
{
    public const string RESULT_NOOP = 'noop';

    public const string RESULT_MOVE = 'move';

    public const string RESULT_SWAP = 'swap';

    public function __construct(
        private readonly SlotParticipationConstraintService $participationConstraints,
    ) {}

    /**
     * Move or swap a scheduled activity between slots on the same event.
     *
     * @throws ValidationException
     */
    public function moveOrSwap(Event $event, Slot $source, Slot $target): string
    {
        if ((int) $source->id === (int) $target->id) {
            return self::RESULT_NOOP;
        }

        if ((int) $source->event_id !== (int) $event->id || (int) $target->event_id !== (int) $event->id) {
            abort(404);
        }

        $source->loadMissing(['activity', 'activityTypes']);
        $target->loadMissing(['activity', 'activityTypes']);

        $activityA = $source->activity;
        if ($activityA === null || $activityA->isCancelled()) {
            throw ValidationException::withMessages([
                'activity_id' => [__('validation.exists', ['attribute' => 'activity_id'])],
            ]);
        }

        $activityB = $target->activity;
        if ($activityB !== null && $activityB->isCancelled()) {
            throw ValidationException::withMessages([
                'slot_id' => [__('validation.exists', ['attribute' => 'slot_id'])],
            ]);
        }

        $isSwap = $activityB !== null;

        if ($isSwap) {
            $this->assertActivityFitsSlot($activityA, $target);
            $this->assertActivityFitsSlot($activityB, $source);
        } else {
            $this->assertActivityFitsSlot($activityA, $target);
        }

        return DB::transaction(function () use ($event, $source, $target, $activityA, $activityB, $isSwap): string {
            if ($isSwap) {
                $source->update(['activity_id' => null]);
                $target->update(['activity_id' => $activityA->id]);
                $source->update(['activity_id' => $activityB->id]);

                $this->syncAcceptedProposalSlot($event, $activityA, (int) $target->id);
                $this->syncAcceptedProposalSlot($event, $activityB, (int) $source->id);

                $this->participationConstraints->applyForcedSettingsToActivity($source->fresh(), $activityB);
                $this->participationConstraints->applyForcedSettingsToActivity($target->fresh(), $activityA);

                return self::RESULT_SWAP;
            }

            $source->update(['activity_id' => null]);
            $target->update(['activity_id' => $activityA->id]);

            $this->syncAcceptedProposalSlot($event, $activityA, (int) $target->id);
            $this->participationConstraints->applyForcedSettingsToActivity($target->fresh(), $activityA);

            return self::RESULT_MOVE;
        });
    }

    private function assertActivityFitsSlot(Activity $activity, Slot $slot): void
    {
        if (! $slot->fitsProposalActivity($activity)) {
            throw ValidationException::withMessages([
                'slot_id' => [__('ui.status.slot_activity_type_or_duration_mismatch')],
            ]);
        }

        if (! $this->participationConstraints->activityMatchesSlot($activity, $slot)) {
            throw ValidationException::withMessages([
                'slot_id' => [__('ui.slots.activity_participation_mismatch')],
            ]);
        }
    }

    private function syncAcceptedProposalSlot(Event $event, Activity $activity, int $slotId): void
    {
        $proposal = ActivityProposal::query()
            ->where('event_id', $event->id)
            ->where('activity_id', $activity->id)
            ->where('status', ActivityProposalStatus::Accepted)
            ->first();

        if ($proposal === null) {
            return;
        }

        $proposal->update(['accepted_slot_id' => $slotId]);
    }
}
