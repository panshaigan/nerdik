<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\EventInstance;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityProposalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $proposals = ActivityProposal::with(['activity', 'eventInstance.event', 'creator', 'acceptedSlot'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('activity-proposals.index', compact('proposals'));
    }

    /**
     * Show the form for proposing an activity to an event instance.
     */
    public function create(EventInstance $eventInstance)
    {
        $eventInstance->load('event', 'slots');
        $myActivities = Activity::where('host_user_id', Auth::id())->orderBy('name')->get();

        return view('activity-proposals.create', [
            'instance' => $eventInstance,
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
            'event_instance_id' => ['required', 'exists:event_instances,id'],
            'preferred_start_time' => ['nullable', 'date'],
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => ['integer', 'exists:slots,id'],
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);
        $instance = EventInstance::findOrFail($validated['event_instance_id']);

        $proposal = ActivityProposal::create([
            'activity_id' => $activity->id,
            'event_instance_id' => $instance->id,
            'created_by' => Auth::id(),
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'status' => 'pending',
        ]);

        // Attach compatible slots if provided
        if (! empty($validated['slot_ids'])) {
            $slots = Slot::whereIn('id', $validated['slot_ids'])
                ->where('event_instance_id', $instance->id)
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
            }
        }

        return redirect()->route('event-instances.show', $instance)
            ->with('status', __('Proposal submitted.'));
    }

    /**
     * Accept a proposal: assign the activity to the chosen slot.
     */
    public function accept(Request $request, ActivityProposal $proposal)
    {
        $instance = $proposal->eventInstance;
        if ($instance->event->created_by !== Auth::id()) {
            abort(403, __('Only the event owner can accept proposals.'));
        }
        if ($proposal->status !== 'pending') {
            return redirect()->back()->with('status', __('Proposal is not pending.'));
        }

        $validated = $request->validate([
            'slot_id' => ['required', 'exists:slots,id'],
        ]);

        $slot = Slot::where('id', $validated['slot_id'])
            ->where('event_instance_id', $instance->id)
            ->whereNull('activity_id')
            ->firstOrFail();

        $proposal->update([
            'status' => 'accepted',
            'accepted_slot_id' => $slot->id,
        ]);
        $slot->update(['activity_id' => $proposal->activity_id]);

        $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'eventInstance.event'])));

        return redirect()->route('event-instances.show', $instance)
            ->with('status', __('Proposal accepted.'));
    }

    /**
     * Reject a proposal.
     */
    public function reject(ActivityProposal $proposal)
    {
        $instance = $proposal->eventInstance;
        if ($instance->event->created_by !== Auth::id()) {
            abort(403, __('Only the event owner can reject proposals.'));
        }
        if ($proposal->status !== 'pending') {
            return redirect()->back()->with('status', __('Proposal is not pending.'));
        }

        $proposal->update(['status' => 'rejected']);

        $proposal->creator?->notify(new ProposalRejectedNotification($proposal->fresh(['activity', 'eventInstance.event'])));

        return redirect()->route('event-instances.show', $instance)
            ->with('status', __('Proposal rejected.'));
    }
}
