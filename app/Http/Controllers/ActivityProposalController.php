<?php

namespace App\Http\Controllers;

use App\Models\ActivityProposal;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityProposalController extends Controller
{
    /**
     * Accept a proposal: assign the activity to the chosen slot.
     */
    public function accept(Request $request, ActivityProposal $proposal)
    {
        $event = $proposal->event;
        if ($event->created_by !== Auth::id()) {
            abort(403, __('ui.status.forbidden_accept'));
        }
        if ($proposal->status !== 'pending') {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $validated = $request->validate([
            'slot_id' => ['required', 'exists:slots,id'],
        ]);

        $slot = Slot::where('id', $validated['slot_id'])
            ->where('event_id', $event->id)
            ->whereNull('activity_id')
            ->firstOrFail();

        $proposal->update([
            'status' => 'accepted',
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
        if ($event->created_by !== Auth::id()) {
            abort(403, __('ui.status.forbidden_reject'));
        }
        if ($proposal->status !== 'pending') {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $proposal->update(['status' => 'rejected']);

        $proposal->creator?->notify(new ProposalRejectedNotification($proposal->fresh(['activity', 'event'])));

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_rejected'));
    }
}
