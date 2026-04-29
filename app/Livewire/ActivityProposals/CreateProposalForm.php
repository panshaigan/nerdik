<?php

namespace App\Livewire\ActivityProposals;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Services\ActivityProposalFlowService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateProposalForm extends Component
{
    use AuthorizesOwnership;

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
            'activity_id' => [
                'required',
                Rule::exists('activities', 'id')->where(fn ($query) => $query->where('created_by', Auth::id())),
            ],
            'preferred_start_time' => ['nullable', 'date'],
            'slot_ids' => ['nullable', 'array'],
            'slot_ids.*' => ['integer'],
        ];
    }

    public function save()
    {
        $validated = $this->validate($this->rules());

        $activity = Activity::findOrFail($validated['activity_id']);
        $this->authorizeCreatedBy($activity);
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
        $requestedSlotIds = array_values(array_unique(array_map('intval', $validated['slot_ids'] ?? [])));
        $validSlotIds = Slot::query()
            ->where('event_id', $event->id)
            ->whereNull('activity_id')
            ->whereIn('id', $requestedSlotIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $flow->notifyHostOfNewProposal($proposal);
        $flow->attachProposedSlotsAndTryAutoAccept(
            $proposal,
            $event,
            $activity,
            $validSlotIds
        );

        session()->flash('status', __('ui.status.proposal_submitted'));

        return redirect()->route('events.show', $event);
    }

    public function render()
    {
        $this->event->loadMissing('slots');

        $myActivities = Activity::with('activityType')->where('created_by', Auth::id())->orderBy('name')->get();

        return view('livewire.activity-proposals.create-proposal-form', [
            'myActivities' => $myActivities,
        ]);
    }
}
