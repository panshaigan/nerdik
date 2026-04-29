<?php

namespace App\Livewire\ActivityProposals;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Services\ActivityProposalFlowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

    public function save(): RedirectResponse
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

        $flow = app(ActivityProposalFlowService::class);
        $flow->notifyHostOfNewProposal($proposal);
        $flow->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            $validated['slot_ids'] ?? []
        );

        session()->flash('status', __('ui.status.proposal_submitted'));

        return redirect()->route('events.show', $event);
    }

    public function render(): View
    {
        $this->event->loadMissing('slots');

        $myActivities = Activity::with('activityType')->where('created_by', Auth::id())->orderBy('name')->get();

        return view('livewire.activity-proposals.create-proposal-form', [
            'myActivities' => $myActivities,
        ]);
    }
}
