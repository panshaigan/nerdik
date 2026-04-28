<?php

namespace App\Livewire\Events;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityProposalDecisionService;
use App\Services\EventSlotPresentationService;
use App\Services\SlotScheduleSyncService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowEvent extends Component
{
    use AuthorizesOwnership;

    public int $eventId;
    public string $tab = 'description';

    protected array $queryString = [
        'tab' => ['except' => 'description'],
    ];

    /** Bumped when slots change via async JS so the component re-renders. */
    public int $slotListVersion = 0;

    /** Free slots marked for “Propose an activity” preferred slots (slot ids). */
    public array $proposalSlotIds = [];

    /**
     * Pending proposal accept: slot id per proposal (empty string = auto-assign). Keys are proposal ids.
     *
     * @var array<int|string, int|string>
     */
    public array $proposalAcceptSlotId = [];

    /** @var array<int|string, string> */
    public array $slotCancelReason = [];

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
        $this->tab = $this->normalizeTab($this->tab);
        $event->load(['slots.activity']);
        app(SlotScheduleSyncService::class)->syncSlotEndsForEvent($event);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    public function toggleProposalSlot(int $slotId): void
    {
        $slot = Slot::query()
            ->whereKey($slotId)
            ->where('event_id', $this->eventId)
            ->whereNull('activity_id')
            ->first();
        if ($slot === null) {
            return;
        }

        $ids = array_map('intval', $this->proposalSlotIds);
        $key = array_search($slotId, $ids, true);
        if ($key !== false) {
            unset($ids[$key]);
            $this->proposalSlotIds = array_values($ids);
        } else {
            $this->proposalSlotIds = [...$ids, $slotId];
        }
    }

    #[On('slot-mutations-refresh')]
    public function refreshAfterSlotMutation(): void
    {
        $this->slotListVersion++;
        $event = Event::query()->whereKey($this->eventId)->first();
        if ($event !== null) {
            $event->loadMissing(['slots.activity']);
            app(SlotScheduleSyncService::class)->syncSlotEndsForEvent($event);
            $event->loadMissing('slots');
            $freeIds = $event->slots->whereNull('activity_id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->proposalSlotIds = array_values(array_intersect(
                array_map('intval', $this->proposalSlotIds),
                $freeIds
            ));
        }
    }

    public function deleteEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);
        $event->delete();
        session()->flash('status', __('Event deleted.'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function deleteSlot(int $slotId): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()->whereKey($slotId)->firstOrFail();

        if ((int) $slot->event_id !== (int) $event->id) {
            abort(404);
        }

        $this->authorizeCreatedBy($slot);
        $slot->delete();
        $this->proposalSlotIds = array_values(array_filter(
            $this->proposalSlotIds,
            fn ($id) => (int) $id !== (int) $slotId
        ));
        $this->syncSlotEndsForThisEvent();
    }

    public function detachActivityFromSlot(int $slotId, ActivityProposalDecisionService $decisions): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);

        $slot = Slot::query()
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        if ($slot->activity_id === null) {
            return;
        }

        try {
            $decisions->detachActivityFromSlot($event, $slot);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('detachActivityFromSlot', $message);
                }
            }

            return;
        }

        $this->refreshAfterSlotMutation();
        session()->flash('status', __('ui.status.activity_detached_from_slot'));
    }

    public function cancelSlotActivity(int $slotId, ActivityHostingModeService $hostingModes): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()
            ->with('activity')
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $activity = $slot->activity;
        if ($activity === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user?->canModifyEntity($event) || $user?->canModifyEntity($activity), 403);

        $reason = $this->slotCancelReason[$slotId]
            ?? $this->slotCancelReason[(string) $slotId]
            ?? null;
        $reason = is_string($reason) ? trim($reason) : null;
        if ($reason === '') {
            $reason = null;
        }

        if ($reason !== null && mb_strlen($reason) > 1000) {
            $this->addError('slotCancelReason.'.$slotId, __('validation.max.string', [
                'attribute' => 'cancel_reason',
                'max' => 1000,
            ]));

            return;
        }

        $hostingModes->cancel($activity, $user, $reason);
        unset($this->slotCancelReason[$slotId], $this->slotCancelReason[(string) $slotId]);
        $this->slotListVersion++;
        $this->syncSlotEndsForThisEvent();
        session()->flash('status', __('ui.activities.cancelled_status'));
    }

    public function reopenSlotActivity(int $slotId, ActivityHostingModeService $hostingModes): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()
            ->with('activity')
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $activity = $slot->activity;
        if ($activity === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user?->canModifyEntity($event) || $user?->canModifyEntity($activity), 403);

        $hostingModes->reopen($activity);
        $this->slotListVersion++;
        $this->syncSlotEndsForThisEvent();
        session()->flash('status', __('ui.activities.reopened_status'));
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
            session()->flash('status', __('ui.status.proposal_not_pending'));

            return;
        }

        $rawSlotId = $this->proposalAcceptSlotId[$proposalId]
            ?? $this->proposalAcceptSlotId[(string) $proposalId]
            ?? '';

        try {
            $decisions->accept($proposal, $rawSlotId);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('proposalAcceptSlot.'.$proposalId, $message);
                }
            }

            return;
        }

        unset($this->proposalAcceptSlotId[$proposalId], $this->proposalAcceptSlotId[(string) $proposalId]);
        $this->slotListVersion++;
        $this->syncSlotEndsForThisEvent();
        session()->flash('status', __('ui.status.proposal_accepted'));
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
            session()->flash('status', __('ui.status.proposal_not_pending'));

            return;
        }

        $decisions->reject($proposal);
        $this->slotListVersion++;
        session()->flash('status', __('ui.status.proposal_rejected'));
    }

    protected function syncSlotEndsForThisEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->first();
        if ($event === null) {
            return;
        }
        $event->load(['slots.activity']);
        app(SlotScheduleSyncService::class)->syncSlotEndsForEvent($event);
    }

    public function render(EventSlotPresentationService $slotPresentation)
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'creator',
            'organization',
            'places',
            'enrollmentWindows',
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity.tags.translations',
                'activity.tags.tagCategory',
                'activity.activityType',
                'activity.canceller',
                'activityTypes',
            ])->orderBy('starts_at'),
        ]);

        $enrollment = $slotPresentation->enrollmentPresentation($event, now());
        $activeEnrollmentWindow = $enrollment->activeWindow;
        $activeWindowRemainingByActivityId = $enrollment->remainingByActivityId;

        $slotHourGroups = $slotPresentation->slotHourGroupsForEvent($event);
        $pendingProposals = $event->proposals()
            ->with(['activity.tags.translations', 'activity.tags.tagCategory', 'activity.activityType', 'creator', 'proposedSlots'])
            ->where('status', ActivityProposalStatus::Pending)
            ->orderBy('created_at')
            ->get();
        $user = auth()->user();
        $canManageEvent = $user !== null && $user->canModifyEntity($event);

        $slotNameSuggestions = [];
        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        $slotBaseNameSuggestions = [];
        if ($canManageEvent) {
            $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
            $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

            $slotMassVenues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            $venueIds = $slotMassVenues->pluck('id');
            if ($venueIds->isNotEmpty()) {
                $children = Place::query()
                    ->whereIn('parent_id', $venueIds)
                    ->where('type', 'room')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
                foreach ($slotMassVenues as $v) {
                    $slotMassRoomsByVenueId[$v->id] = ($children[$v->id] ?? collect())
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                        ->values()
                        ->all();
                }
            }
        }

        return view('livewire.events.show-event', [
            'event' => $event,
            'activeEnrollmentWindow' => $activeEnrollmentWindow,
            'activeWindowRemainingByActivityId' => $activeWindowRemainingByActivityId,
            'pendingProposals' => $pendingProposals,
            'canManageEvent' => $canManageEvent,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotHourGroups' => $slotHourGroups,
        ]);
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['description', 'plan', 'proposals'], true) ? $value : 'description';
    }
}
