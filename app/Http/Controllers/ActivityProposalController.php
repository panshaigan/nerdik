<?php

namespace App\Http\Controllers;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityProposalController extends Controller
{
    /**
     * Accept a proposal: assign the activity to the chosen slot, or auto-pick a fitting slot
     * (preferred slots first, then any random fitting free slot).
     */
    public function accept(Request $request, ActivityProposal $proposal)
    {
        $event = $proposal->event;
        abort_unless(Auth::user()?->canModifyEntity($event), 403, __('ui.status.forbidden_accept'));
        if ($proposal->status !== ActivityProposalStatus::Pending) {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $proposal->loadMissing('activity');

        $rawSlotId = $request->input('slot_id');
        $wantsAuto = $rawSlotId === null || $rawSlotId === '';

        if ($wantsAuto) {
            $slot = $this->resolveSlotForAutoAccept($proposal, $event);
            if ($slot === null) {
                return redirect()->back()->withErrors([
                    'slot_id' => __('ui.status.no_fitting_slot_for_proposal'),
                ]);
            }
        } else {
            $validated = $request->validate([
                'slot_id' => ['required', 'integer', 'exists:slots,id'],
            ]);

            $slot = Slot::query()
                ->whereKey($validated['slot_id'])
                ->where('event_id', $event->id)
                ->whereNull('activity_id')
                ->with('activityTypes')
                ->firstOrFail();

            if (! $slot->fitsProposalActivity($proposal->activity)) {
                return redirect()->back()->withErrors([
                    'slot_id' => __('ui.status.slot_activity_type_or_duration_mismatch'),
                ]);
            }
        }

        $proposal->update([
            'status' => ActivityProposalStatus::Accepted,
            'accepted_slot_id' => $slot->id,
        ]);
        $slot->update(['activity_id' => $proposal->activity_id]);

        $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'event'])));

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_accepted'));
    }

    /**
     * Reject a proposal.
     */
    public function reject(ActivityProposal $proposal)
    {
        $event = $proposal->event;
        abort_unless(Auth::user()?->canModifyEntity($event), 403, __('ui.status.forbidden_reject'));
        if ($proposal->status !== ActivityProposalStatus::Pending) {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $proposal->update(['status' => ActivityProposalStatus::Rejected]);

        $proposal->creator?->notify(new ProposalRejectedNotification($proposal->fresh(['activity', 'event'])));

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_rejected'));
    }

    /**
     * Pick a free slot that fits the activity: random among fitting preferred slots, else random among all fitting free slots.
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
