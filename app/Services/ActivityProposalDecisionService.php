<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use Illuminate\Validation\ValidationException;

class ActivityProposalDecisionService
{
    /**
     * Accept a proposal: assign to chosen slot or auto-pick a fitting free slot.
     * Caller must ensure the proposal is still pending (otherwise redirect with status).
     *
     * @param  mixed  $rawSlotId  Empty / null means auto.
     *
     * @throws ValidationException
     */
    public function accept(ActivityProposal $proposal, mixed $rawSlotId): void
    {
        $proposal->loadMissing('activity');
        $event = $proposal->event;

        $wantsAuto = $rawSlotId === null || $rawSlotId === '';

        if ($wantsAuto) {
            $slot = $this->resolveSlotForAutoAccept($proposal, $event);
            if ($slot === null) {
                throw ValidationException::withMessages([
                    'slot_id' => [__('ui.status.no_fitting_slot_for_proposal')],
                ]);
            }
        } else {
            $slot = Slot::query()
                ->whereKey((int) $rawSlotId)
                ->where('event_id', $event->id)
                ->whereNull('activity_id')
                ->with('activityTypes')
                ->first();

            if ($slot === null) {
                throw ValidationException::withMessages([
                    'slot_id' => [__('validation.exists', ['attribute' => 'slot_id'])],
                ]);
            }

            if (! $slot->fitsProposalActivity($proposal->activity)) {
                throw ValidationException::withMessages([
                    'slot_id' => [__('ui.status.slot_activity_type_or_duration_mismatch')],
                ]);
            }
        }

        $proposal->update([
            'status' => ActivityProposalStatus::Accepted,
            'accepted_slot_id' => $slot->id,
        ]);
        $slot->update(['activity_id' => $proposal->activity_id]);

        $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'event'])));
    }

    /** Caller must ensure the proposal is still pending. */
    public function reject(ActivityProposal $proposal): void
    {
        $proposal->update(['status' => ActivityProposalStatus::Rejected]);

        $proposal->creator?->notify(new ProposalRejectedNotification($proposal->fresh(['activity', 'event'])));
    }

    /**
     * Random preferred fitting slot, else random fitting free slot.
     */
    private function resolveSlotForAutoAccept(ActivityProposal $proposal, Event $event): ?Slot
    {
        $activity = $proposal->activity;

        $preferred = $proposal->proposedSlots()
            ->where('event_id', $event->id)
            ->whereNull('activity_id')
            ->with('activityTypes')
            ->get()
            ->filter(fn (Slot $s) => $s->fitsProposalActivity($activity));

        if ($preferred->isNotEmpty()) {
            return $preferred->random();
        }

        $candidates = Slot::query()
            ->where('event_id', $event->id)
            ->whereNull('activity_id')
            ->with('activityTypes')
            ->get()
            ->filter(fn (Slot $s) => $s->fitsProposalActivity($activity));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->random();
    }
}
