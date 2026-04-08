<?php

namespace App\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalSubmittedNotification;

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
     * Sync chosen free slots to the proposal and auto-accept when a matching no-approval slot fits the activity.
     *
     * @param  list<int>  $requestedSlotIds
     */
    public function attachProposedSlotsAndTryAutoAccept(
        ActivityProposal $proposal,
        Event $event,
        Activity $activity,
        array $requestedSlotIds
    ): void {
        if ($requestedSlotIds === []) {
            $this->hostingModes->markProposedToEvent($activity);
            return;
        }

        $validIds = Slot::query()
            ->where('event_id', $event->id)
            ->whereNull('activity_id')
            ->whereIn('id', $requestedSlotIds)
            ->pluck('id')
            ->all();

        $proposal->proposedSlots()->sync($validIds);
        $this->hostingModes->markProposedToEvent($activity);

        $slots = Slot::whereIn('id', $validIds)->get();
        $autoSlot = $slots->firstWhere('requires_approval', false);
        if (! $autoSlot) {
            return;
        }

        $autoSlot->loadMissing('activityTypes');
        if (! $autoSlot->fitsProposalActivity($activity)) {
            return;
        }

        $this->decisions->accept($proposal, $autoSlot->id);
    }
}
