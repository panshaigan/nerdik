<?php

namespace App\Livewire\Events;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityProposalDecisionService;
use App\Services\CancellationNotificationDispatcher;
use App\Services\EventSlotPresentationService;
use App\Services\SlotScheduleSyncService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowEvent extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithUiConfirmModal;

    public int $eventId;

    public string $tab = 'description';

    protected array $queryString = [
        'tab' => ['except' => 'description'],
    ];

    /** Bumped when slots change via async JS so the component re-renders. */
    public int $slotListVersion = 0;

    /** Bumped when the organizer receives a live proposal submission for this event. */
    public int $organizerProposalRefreshTick = 0;

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

    public ?string $eventCancelReason = null;

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

    public function addInterest(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        if ($event->isCancelled()) {
            $this->warning(__('ui.events.signup_blocked_cancelled'));

            return;
        }
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedEvents()->syncWithoutDetaching([$event->id]);
        $this->success(__('ui.interests.added_event'));
    }

    public function removeInterest(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedEvents()->detach($event->id);
        $this->warning(__('ui.interests.removed_event'));
    }

    public function addActivityInterest(int $activityId): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        if ($event->isCancelled()) {
            $this->warning(__('ui.events.signup_blocked_cancelled'));

            return;
        }
        $activity = Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($q) => $q->where('event_id', $event->id))
            ->firstOrFail();

        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedActivities()->syncWithoutDetaching([$activity->id]);
        $user->interestedEvents()->syncWithoutDetaching([$event->id]);
        $this->success(__('ui.interests.added_activity'));
    }

    public function removeActivityInterest(int $activityId): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $activity = Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($q) => $q->where('event_id', $event->id))
            ->firstOrFail();

        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedActivities()->detach($activity->id);
        $this->warning(__('ui.interests.removed_activity'));
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
        if ($event->hasSignupPressure() && ! $event->isCancelled()) {
            $this->warning(__('ui.events.delete_forbidden_use_cancel'));

            return;
        }

        $cancelledBy = auth()->user();
        if ($cancelledBy !== null && ! $event->isCancelled()) {
            app(CancellationNotificationDispatcher::class)->notifyEventCancelled($event, $cancelledBy);
        }
        $event->delete();
        $this->success(__('Event deleted.'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function cancelEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);
        if ($event->isCancelled()) {
            return;
        }
        if (! $event->hasSignupPressure()) {
            $this->warning(__('ui.events.cancel_only_when_roster_present'));

            return;
        }

        $user = auth()->user();
        abort_unless($user !== null, 403);

        $reason = $this->eventCancelReason !== null ? trim($this->eventCancelReason) : null;
        if ($reason === '') {
            $reason = null;
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            $this->addError('eventCancelReason', __('validation.max.string', [
                'attribute' => 'cancel_reason',
                'max' => 1000,
            ]));

            return;
        }

        $event->update([
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'cancel_reason' => $reason,
        ]);
        $this->eventCancelReason = null;

        app(CancellationNotificationDispatcher::class)->notifyEventCancelled($event->fresh(), $user);
        $this->success(__('ui.events.cancelled_status'));
    }

    public function reopenEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);

        if (! $event->isCancelled()) {
            return;
        }

        $event->update([
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
        ]);
        $this->success(__('ui.events.reopened_status'));
    }

    public function confirmDeleteEvent(): void
    {
        $this->openConfirm(
            'delete_event',
            __('Delete'),
            __('Are you sure you want to delete this event?'),
        );
    }

    public function confirmCancelEvent(): void
    {
        $this->openConfirm(
            'cancel_event',
            __('ui.events.cancel_action'),
            __('ui.events.cancel_confirm'),
        );
    }

    public function confirmReopenEvent(): void
    {
        $this->openConfirm(
            'reopen_event',
            __('ui.events.reopen_action'),
            __('ui.events.reopen_confirm'),
        );
    }

    public function confirmDeleteSlot(int $slotId): void
    {
        $this->openConfirm(
            'delete_slot',
            __('Delete'),
            __('Are you sure?'),
            null,
            $slotId,
        );
    }

    public function confirmDetachActivityFromSlot(int $slotId): void
    {
        $this->openConfirm(
            'detach_activity_from_slot',
            __('ui.events.detach_activity_from_slot'),
            __('ui.events.detach_activity_from_slot_confirm'),
            null,
            $slotId,
        );
    }

    public function confirmCancelSlotActivity(int $slotId): void
    {
        $this->openConfirm(
            'cancel_slot_activity',
            __('ui.activities.cancel_action'),
            __('ui.activities.cancel_confirm'),
            null,
            $slotId,
        );
    }

    public function confirmReopenSlotActivity(int $slotId): void
    {
        $this->openConfirm(
            'reopen_slot_activity',
            __('ui.activities.reopen_action'),
            __('ui.activities.reopen_confirm'),
            null,
            $slotId,
        );
    }

    public function runConfirmedAction(
        ActivityProposalDecisionService $decisions,
        ActivityHostingModeService $hostingModes
    ): void {
        $action = $this->pendingAction;
        $slotId = $this->pendingContextId;
        $this->closeConfirm();

        if ($action === null) {
            return;
        }

        match ($action) {
            'delete_event' => $this->deleteEvent(),
            'cancel_event' => $this->cancelEvent(),
            'reopen_event' => $this->reopenEvent(),
            'delete_slot' => $slotId !== null ? $this->deleteSlot($slotId) : null,
            'detach_activity_from_slot' => $slotId !== null ? $this->detachActivityFromSlot($slotId, $decisions) : null,
            'cancel_slot_activity' => $slotId !== null ? $this->cancelSlotActivity($slotId, $hostingModes) : null,
            'reopen_slot_activity' => $slotId !== null ? $this->reopenSlotActivity($slotId, $hostingModes) : null,
            default => null,
        };
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
        $this->success(__('Slot deleted.'));
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
            $messages = collect($e->errors())->flatten()->filter()->values();
            if ($messages->isNotEmpty()) {
                $this->warning((string) $messages->first());
            } else {
                $this->warning(__('ui.status.oops'));
            }

            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    // Keep the errors available for inline consumers, but show feedback as toast.
                    $this->addError('detachActivityFromSlot', $message);
                }
            }

            return;
        }

        $this->refreshAfterSlotMutation();
        $this->success(__('ui.status.activity_detached_from_slot'));
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

        $rawReason = $this->slotCancelReason[$slotId]
            ?? $this->slotCancelReason[(string) $slotId]
            ?? null;
        $reason = is_string($rawReason) ? trim($rawReason) : null;
        if ($reason === '') {
            $reason = null;
        }
        $reasonValidation = Validator::make(
            ['reason' => $reason],
            ['reason' => ['nullable', 'string', 'max:1000']]
        );
        if ($reasonValidation->fails()) {
            foreach ($reasonValidation->errors()->all() as $message) {
                $this->addError('slotCancelReason.'.$slotId, $message);
            }

            return;
        }
        $reason = $reasonValidation->validated()['reason'] ?? null;

        $hostingModes->cancel($activity, $user, $reason);
        unset($this->slotCancelReason[$slotId], $this->slotCancelReason[(string) $slotId]);
        $this->slotListVersion++;
        $this->syncSlotEndsForThisEvent();
        $this->success(__('ui.activities.cancelled_status'));
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
        $this->success(__('ui.activities.reopened_status'));
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
        $this->slotListVersion++;
        $this->syncSlotEndsForThisEvent();
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
        $this->slotListVersion++;
        $this->success(__('ui.status.proposal_rejected'));
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
            'canceller',
            'organization',
            'places',
            'enrollmentWindows',
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity' => fn ($aq) => $aq->with([
                    'tags.translations',
                    'tags.tagCategory',
                    'activityType',
                    'canceller',
                ])->withCount([
                    'participants',
                    'interestedUsers',
                ]),
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
        $hasInterest = $user !== null
            ? $user->interestedEvents()->whereKey($event->id)->exists()
            : false;
        $interestedActivityIds = $user !== null
            ? $user->interestedActivities()
                ->whereIn('activities.id', $event->slots->pluck('activity_id')->filter()->all())
                ->pluck('activities.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $confirmedActivities = $event->slots
            ->pluck('activity')
            ->filter(fn ($activity) => $activity !== null && ! $activity->isCancelled())
            ->values();
        $confirmedActivitiesCount = $confirmedActivities->count();
        $confirmedParticipantsCount = (int) $confirmedActivities->sum(
            fn ($activity) => (int) ($activity->participants_count ?? 0)
        );
        $interestedPeopleCount = (int) $event->interestedUsers()->count();

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
            'eventSignupPressureBlocksDelete' => $event->hasSignupPressure() && ! $event->isCancelled(),
            'activeEnrollmentWindow' => $activeEnrollmentWindow,
            'activeWindowRemainingByActivityId' => $activeWindowRemainingByActivityId,
            'pendingProposals' => $pendingProposals,
            'canManageEvent' => $canManageEvent,
            'hasInterest' => $hasInterest,
            'interestedActivityIds' => $interestedActivityIds,
            'confirmedActivitiesCount' => $confirmedActivitiesCount,
            'confirmedParticipantsCount' => $confirmedParticipantsCount,
            'interestedPeopleCount' => $interestedPeopleCount,
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
