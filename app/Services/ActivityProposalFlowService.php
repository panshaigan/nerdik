<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Validation\ValidationException;

class ActivityProposalFlowService
{
    public function __construct(
        private readonly ActivityProposalDecisionService $decisions,
        private readonly ActivityHostingModeService $hostingModes
    ) {}

    public function notifyHostOfNewProposal(ActivityProposal $proposal): void
    {
        $proposal->loadMissing('event');
        $event = $proposal->event;
        if ($event === null) {
            return;
        }

        $submitterId = $proposal->created_by;
        if ((int) $event->created_by === (int) $submitterId) {
            return;
        }

        $event->creator?->notify(new ProposalSubmittedNotification($proposal));
    }

    /**
     * Sync chosen free slots to the proposal and auto-accept when appropriate:
     * preferred no-approval slots first, then organizer self-proposals (any fitting free slot).
     *
     * @param  list<int>  $requestedSlotIds
     */
    public function attachProposedSlotsAndTryAutoAccept(
        ActivityProposal $proposal,
        Event $event,
        Activity $activity,
        array $requestedSlotIds
    ): void {
        $validIds = [];

        if ($requestedSlotIds !== []) {
            $validIds = Slot::query()
                ->where('event_id', $event->id)
                ->whereNull('activity_id')
                ->whereIn('id', $requestedSlotIds)
                ->pluck('id')
                ->all();

            $proposal->proposedSlots()->sync($validIds);
        }

        $this->hostingModes->markProposedToEvent($activity);

        if ($validIds !== []) {
            $slots = Slot::whereIn('id', $validIds)->get();
            $autoSlot = $slots->firstWhere('requires_approval', false);
            if ($autoSlot) {
                $autoSlot->loadMissing('activityTypes');
                if (
                    $autoSlot->fitsProposalActivity($activity)
                    && $this->decisions->activityMatchesSlotForAccept($activity, $autoSlot)
                ) {
                    $this->decisions->accept($proposal, $autoSlot->id);

                    return;
                }
            }
        }

        $this->tryAutoAcceptOrganizerSelfProposal($proposal, $event);
    }

    /**
     * Event owners do not need to approve their own proposals: auto-pick a fitting free slot.
     */
    private function tryAutoAcceptOrganizerSelfProposal(ActivityProposal $proposal, Event $event): void
    {
        $proposal->refresh();
        if ($proposal->status !== ActivityProposalStatus::Pending) {
            return;
        }

        if ((int) $event->created_by !== (int) $proposal->created_by) {
            return;
        }

        try {
            $this->decisions->accept($proposal, null);
        } catch (ValidationException) {
            // No fitting free slot — leave pending for the organizer to resolve later.
        }
    }
}
