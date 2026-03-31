<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityProposalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $proposals = ActivityProposal::with(['activity', 'event', 'creator', 'acceptedSlot'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('activity-proposals.index', compact('proposals'));
    }

    /**
     * Show the form for proposing an activity to an event.
     */
    public function create(Event $event)
    {
        $event->load('slots');
        $myActivities = Activity::where('host_user_id', Auth::id())->orderBy('name')->get();

        return view('activity-proposals.create', [
            'event' => $event,
            'myActivities' => $myActivities,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'event_id' => ['required', 'exists:events,id'],
            'preferred_start_time' => ['nullable', 'date'],
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => ['integer', 'exists:slots,id'],
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);
        $event = Event::findOrFail($validated['event_id']);

        $proposal = ActivityProposal::create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => Auth::id(),
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'status' => 'pending',
        ]);
        $proposal->load(['activity', 'event', 'creator']);

        // Notify event owner about new incoming proposal.
        if ($event->created_by !== Auth::id()) {
            $event->creator?->notify(new ProposalSubmittedNotification($proposal));
        }

        // Attach compatible slots if provided
        if (! empty($validated['slot_ids'])) {
            $slots = Slot::whereIn('id', $validated['slot_ids'])
                ->where('event_id', $event->id)
                ->get();

            foreach ($slots as $slot) {
                $proposal->slots()->create([
                    'slot_id' => $slot->id,
                ]);
            }

            // Simple auto-accept: pick first slot without approval requirement
            $autoSlot = $slots->firstWhere('requires_approval', false);

            if ($autoSlot) {
                $proposal->update([
                    'status' => 'accepted',
                    'accepted_slot_id' => $autoSlot->id,
                ]);
                $autoSlot->update(['activity_id' => $activity->id]);

                // Auto-accept path should also notify the proposer.
                $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'event'])));
            }
        }

        return redirect()->route('events.show', $event)
            ->with('status', __('ui.status.proposal_submitted'));
    }

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
