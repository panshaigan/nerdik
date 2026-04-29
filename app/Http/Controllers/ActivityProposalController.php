<?php

namespace App\Http\Controllers;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Services\ActivityProposalDecisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ActivityProposalController extends Controller
{
    /**
     * Accept a proposal: assign the activity to the chosen slot, or auto-pick a fitting free slot
     * (preferred slots first, then any random fitting free slot).
     */
    public function accept(Request $request, ActivityProposal $proposal, ActivityProposalDecisionService $decisions)
    {
        $event = $proposal->event;
        abort_unless(Auth::user()?->canModifyEntity($event), 403, __('ui.status.forbidden_accept'));
        if ($proposal->status !== ActivityProposalStatus::Pending) {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $rawSlotId = $request->input('slot_id');

        try {
            $decisions->accept($proposal, $rawSlotId);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_accepted'));
    }

    /**
     * Reject a proposal.
     */
    public function reject(ActivityProposal $proposal, ActivityProposalDecisionService $decisions)
    {
        $event = $proposal->event;
        abort_unless(Auth::user()?->canModifyEntity($event), 403, __('ui.status.forbidden_reject'));
        if ($proposal->status !== ActivityProposalStatus::Pending) {
            return redirect()->back()->with('status', __('ui.status.proposal_not_pending'));
        }

        $decisions->reject($proposal);

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_rejected'));
    }
}
