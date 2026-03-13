<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\EventInstance;
use App\Models\Slot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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

        return redirect()->back()
            ->with('status', __('Proposal submitted.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityProposal $activityProposal)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ActivityProposal $activityProposal)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityProposal $activityProposal)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityProposal $activityProposal)
    {
        //
    }
}
