<?php

namespace App\Livewire\ActivityProposals;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalSubmittedNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateProposalForm extends Component
{
    public Event $event;

    public ?int $activity_id = null;

    /** @var list<int> */
    public array $slot_ids = [];

    public ?string $preferred_start_time = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'activity_id' => ['required', 'exists:activities,id'],
            'preferred_start_time' => ['nullable', 'date'],
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => ['integer', 'exists:slots,id'],
        ];
    }

    public function save()
    {
        $validated = $this->validate($this->rules());

        $activity = Activity::findOrFail($validated['activity_id']);
        $event = $this->event;

        $proposal = ActivityProposal::create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => Auth::id(),
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'status' => ActivityProposalStatus::Pending,
        ]);
        $proposal->load(['activity', 'event', 'creator']);

        if ($event->created_by !== Auth::id()) {
            $event->creator?->notify(new ProposalSubmittedNotification($proposal));
        }

        if (! empty($validated['slot_ids'])) {
            $slots = Slot::whereIn('id', $validated['slot_ids'])
                ->where('event_id', $event->id)
                ->get();

            foreach ($slots as $slot) {
                $proposal->slots()->create([
                    'slot_id' => $slot->id,
                ]);
            }

            $autoSlot = $slots->firstWhere('requires_approval', false);

            if ($autoSlot) {
                $proposal->update([
                    'status' => ActivityProposalStatus::Accepted,
                    'accepted_slot_id' => $autoSlot->id,
                ]);
                $autoSlot->update(['activity_id' => $activity->id]);

                $proposal->creator?->notify(new ProposalAcceptedNotification($proposal->fresh(['activity', 'event'])));
            }
        }

        session()->flash('status', __('ui.status.proposal_submitted'));

        return redirect()->route('events.show', $event);
    }

    public function render()
    {
        $this->event->loadMissing('slots');

        $myActivities = Activity::where('created_by', Auth::id())->orderBy('name')->get();

        return view('livewire.activity-proposals.create-proposal-form', [
            'myActivities' => $myActivities,
        ]);
    }
}
