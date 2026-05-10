<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Services\ActivityProposalDecisionService;
use App\Services\SlotScheduleSyncService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Pending proposals panel for {@see ShowEvent}. Mounted only when the shell `tab` is `proposals`.
 * Tab selection and `?tab=` live on the parent; do not bind `tab` to the query string here.
 */
#[Lazy]
class EventShowProposalsTab extends Component
{
    use AuthorizesOwnership;
    use Toast;

    public int $eventId;

    /**
     * Mirrors {@see ShowEvent::$tab} from the shell; for debugging/contracts — not read from the request URL.
     */
    public string $activeTab = 'proposals';

    /** Bumped when the organizer receives a live proposal submission for this event. */
    public int $organizerProposalRefreshTick = 0;

    /**
     * Pending proposal accept: slot id per proposal (empty string = auto-assign). Keys are proposal ids.
     *
     * @var array<int|string, int|string>
     */
    public array $proposalAcceptSlotId = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="flex min-h-[16rem] items-center justify-center p-8" data-ui="event-show-proposals-tab-placeholder">
            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
        </div>
        HTML;
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    /**
     * Opens the activity preview modal on the parent {@see ShowEvent} shell.
     */
    public function openActivityPreview(int $activityId): void
    {
        $this->dispatch('open-event-activity-preview', activityId: $activityId);
    }

    #[On('event-proposal-submitted-broadcast')]
    public function refreshOrganizerForIncomingProposal(int|string $eventId): void
    {
        if ((int) $eventId !== (int) $this->eventId) {
            return;
        }

        $event = Event::query()->whereKey($this->eventId)->first();
        if ($event === null) {
            return;
        }

        $user = auth()->user();
        if ($user === null || ! $user->canModifyEntity($event)) {
            return;
        }

        $this->organizerProposalRefreshTick++;
    }

    public function acceptPendingProposal(int $proposalId, ActivityProposalDecisionService $decisions): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($event), 403);

        $proposal = ActivityProposal::query()
            ->whereKey($proposalId)
            ->where('event_id', $this->eventId)
            ->firstOrFail();

        if ($proposal->status !== ActivityProposalStatus::Pending) {
            $this->warning(__('ui.status.proposal_not_pending'));

            return;
        }

        $rawSlotId = $this->proposalAcceptSlotId[$proposalId]
            ?? $this->proposalAcceptSlotId[(string) $proposalId]
            ?? '';
        $normalizedSlotId = ($rawSlotId === '' || $rawSlotId === null) ? null : $rawSlotId;
        $slotValidation = Validator::make(
            ['slot_id' => $normalizedSlotId],
            [
                'slot_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('slots', 'id')->where(
                        fn ($query) => $query
                            ->where('event_id', $event->id)
                            ->whereNull('activity_id')
                    ),
                ],
            ]
        );
        if ($slotValidation->fails()) {
            foreach ($slotValidation->errors()->all() as $message) {
                $this->addError('proposalAcceptSlot.'.$proposalId, $message);
            }

            return;
        }
        $validatedSlotId = $slotValidation->validated()['slot_id'] ?? null;

        try {
            $decisions->accept($proposal, $validatedSlotId ?? '');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('proposalAcceptSlot.'.$proposalId, $message);
                }
            }

            return;
        }

        unset($this->proposalAcceptSlotId[$proposalId], $this->proposalAcceptSlotId[(string) $proposalId]);

        $syncEvent = Event::query()->whereKey($this->eventId)->firstOrFail();
        $syncEvent->load(['slots.activity']);
        app(SlotScheduleSyncService::class)->syncSlotEndsForEvent($syncEvent);

        $this->dispatch('slot-mutations-refresh');
        $this->dispatch('event-show-shell-refresh');
        $this->success(__('ui.status.proposal_accepted'));
    }

    public function rejectPendingProposal(int $proposalId, ActivityProposalDecisionService $decisions): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($event), 403);

        $proposal = ActivityProposal::query()
            ->whereKey($proposalId)
            ->where('event_id', $this->eventId)
            ->firstOrFail();

        if ($proposal->status !== ActivityProposalStatus::Pending) {
            $this->warning(__('ui.status.proposal_not_pending'));

            return;
        }

        $decisions->reject($proposal);
        $this->dispatch('event-show-shell-refresh');
        $this->success(__('ui.status.proposal_rejected'));
    }

    public function render(ActivityBadgeGroupBuilder $badgeGroupBuilder): View
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity' => fn ($aq) => $aq->with([
                    'tags.translations',
                    'tags.tagCategory',
                    'activityType',
                ]),
                'activityTypes',
            ])->orderBy('starts_at'),
        ]);

        $freeSlotsAllForProposals = $event->slots->whereNull('activity_id')->values();

        $pendingProposals = $event->proposals()
            ->with(['activity.tags.translations', 'activity.tags.tagCategory', 'activity.activityType', 'creator', 'proposedSlots'])
            ->where('status', ActivityProposalStatus::Pending)
            ->orderBy('created_at')
            ->get();

        $proposalBadgeItemsByProposalId = [];
        foreach ($pendingProposals as $proposal) {
            $pa = $proposal->activity;
            if ($pa !== null) {
                $proposalBadgeItemsByProposalId[(int) $proposal->id] = $badgeGroupBuilder->build(
                    $pa,
                    ActivityBadgeGroupConfig::eventProposal(),
                );
            }
        }

        return view('livewire.events.event-show-proposals-tab', [
            'event' => $event,
            'pendingProposals' => $pendingProposals,
            'proposalBadgeItemsByProposalId' => $proposalBadgeItemsByProposalId,
            'freeSlotsAllForProposals' => $freeSlotsAllForProposals,
        ]);
    }
}
