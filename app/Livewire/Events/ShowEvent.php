<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Enums\ActivityProposalStatus;
use App\Enums\BadgeSemantic;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityParticipationService;
use App\Services\ActivityParticipationViewService;
use App\Services\ActivityProposalDecisionService;
use App\Services\CancellationNotificationDispatcher;
use App\Services\EventActivitySignupService;
use App\Services\EventProgrammeCancellationSyncService;
use App\Services\EventSlotPresentationService;
use App\Services\SlotScheduleSyncService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    /** Bumped when activity roster changes should refresh plan tab counters. */
    public int $planCounterRefreshTick = 0;

    public bool $activityPreviewModalOpen = false;

    public ?int $previewActivityId = null;

    public string $activityPreviewTab = 'info';

    /** Bumped when the selected activity preview receives a roster broadcast. */
    public int $activityPreviewRefreshTick = 0;

    /** Free slots marked for “Propose an activity” preferred slots (slot ids). */
    public array $proposalSlotIds = [];

    /** Controls whether slots without attached activity are visible on the plan tab. */
    public ?bool $showEmptySlots = null;

    /** Tracks whether empty-slot auto-hide rule is currently active for this viewer. */
    public bool $emptySlotsRestricted = false;

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
        $event->loadMissing('enrollmentWindows');
        $this->eventId = $event->id;
        $this->tab = $this->normalizeTab($this->tab);
        $user = auth()->user();
        if ($this->tab === 'proposals' && ($user === null || ! $user->canModifyEntity($event))) {
            $this->tab = 'description';
        }
        $this->applyShowEmptySlotsPolicy($event, $user);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
        $event = Event::query()->whereKey($this->eventId)->with('enrollmentWindows')->first();
        if ($event !== null) {
            $this->applyShowEmptySlotsPolicy($event, auth()->user());
        }
    }

    public function updatedActivityPreviewTab(string $value): void
    {
        $this->activityPreviewTab = $this->normalizeActivityPreviewTab($value);
        if ($this->activityPreviewTab !== 'participation' || $this->previewActivityId === null) {
            return;
        }

        $activity = Activity::query()
            ->with('slot.event.enrollmentWindows')
            ->whereKey($this->previewActivityId)
            ->first();
        if ($activity === null || ! $this->activityHasActiveEnrollmentWindow($activity, app(EventActivitySignupService::class))) {
            $this->activityPreviewTab = 'info';
        }
    }

    public function updatedActivityPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->closeActivityPreview();
        }
    }

    public function openActivityPreview(int $activityId): void
    {
        $activity = $this->previewActivityQuery($activityId)->firstOrFail();

        $this->previewActivityId = (int) $activity->id;
        $this->activityPreviewTab = 'info';
        $this->activityPreviewModalOpen = true;
    }

    public function closeActivityPreview(): void
    {
        $this->activityPreviewModalOpen = false;
        $this->previewActivityId = null;
        $this->activityPreviewTab = 'info';
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

    public function toggleShowEmptySlots(): void
    {
        $this->showEmptySlots = ! ((bool) $this->showEmptySlots);
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

    public function joinPreviewActivity(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->join($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastFromSessionStatus();
    }

    public function leavePreviewActivity(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->leave($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastFromSessionStatus();
    }

    public function joinPreviewWaitlist(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->joinWaitlist($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastFromSessionStatus();
    }

    public function leavePreviewWaitlist(ActivityParticipationService $participation): void
    {
        $activity = $this->selectedPreviewActivityOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $participation->leaveWaitlist($activity, $user);
        $this->showPreviewParticipationTab();
        $this->toastFromSessionStatus();
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

    #[On('event-plan-activity-participation-updated')]
    public function refreshPlanCountersFromBroadcast(int|string|null $activityId = null): void
    {
        if ($activityId === null) {
            return;
        }

        $activityId = (int) $activityId;
        $belongsToThisEvent = Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($query) => $query->where('event_id', $this->eventId))
            ->exists();

        if (! $belongsToThisEvent) {
            return;
        }

        if ($this->tab === 'plan') {
            $this->planCounterRefreshTick++;
        }

        if ($this->activityPreviewModalOpen && (int) $this->previewActivityId === $activityId) {
            $this->activityPreviewRefreshTick++;
        }
    }

    public function deleteEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);
        if ($event->organiserHardDeleteBlockedWhileActive()) {
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
        if (! $event->qualifiesForExplicitOrganiserCancellation()) {
            $this->warning(__('ui.events.cancel_only_when_programme_nonempty'));

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

        DB::transaction(function () use ($event, $reason, $user): void {
            $event->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);

            app(EventProgrammeCancellationSyncService::class)->cancelScheduledActivitiesForEvent(
                $event,
                $user,
                $reason
            );
        });
        $this->eventCancelReason = null;

        app(CancellationNotificationDispatcher::class)->notifyEventCancelled($event->fresh(), $user);
        $this->success(__('ui.events.cancelled_status'));
        $this->applyShowEmptySlotsPolicy($event->fresh(['enrollmentWindows']), auth()->user());
    }

    public function reopenEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);

        if (! $event->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($event): void {
            app(EventProgrammeCancellationSyncService::class)->reopenActivitiesCancelledWithEvent($event);

            $event->update([
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ]);
        });

        $user = auth()->user();
        abort_unless($user !== null, 403);

        app(CancellationNotificationDispatcher::class)->notifyEventReopened($event->fresh(), $user);
        $this->success(__('ui.events.reopened_status'));
        $this->applyShowEmptySlotsPolicy($event->fresh(['enrollmentWindows']), auth()->user());
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

        $hostingModes->reopen($activity, $user);
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

    public function render(
        EventSlotPresentationService $slotPresentation,
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
    ): View {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'creator',
            'canceller',
            'organization',
            'places',
            'enrollmentWindows',
        ]);

        $enrollment = $slotPresentation->enrollmentPresentation($event, now());
        $activeEnrollmentWindow = $enrollment->activeWindow;
        $activeWindowRemainingByActivityId = $enrollment->remainingByActivityId;

        $user = auth()->user();
        $canManageEvent = $user !== null && $user->canModifyEntity($event);
        $canShowPlanActivityProposalUi = $user !== null
            && ! $event->isCancelled()
            && ($event->starts_at === null || now()->lt($event->starts_at))
            && $activeEnrollmentWindow === null;
        $shouldRestrictEmptySlots = $this->shouldRestrictEmptySlots($event, $user);
        $this->applyShowEmptySlotsPolicy($event, $user);

        $eventActivityIds = $this->slotAttachmentActivityIdsForEvent($event->id);
        [$confirmedActivitiesCount, $confirmedParticipantsCount] = $this->confirmedProgrammeStats($event->id);

        $hasPendingProposals = $canManageEvent && ActivityProposal::query()
            ->where('event_id', $event->id)
            ->where('status', ActivityProposalStatus::Pending)
            ->exists();

        if ($this->tab === 'proposals' && (! $canManageEvent || ! $hasPendingProposals)) {
            $this->tab = 'description';
        }

        $hasInterest = $user !== null
            ? $user->interestedEvents()->whereKey($event->id)->exists()
            : false;
        $interestedActivityIds = $user !== null && $eventActivityIds !== []
            ? $user->interestedActivities()
                ->whereIn('activities.id', $eventActivityIds)
                ->pluck('activities.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $interestedPeopleCount = (int) $event->interestedUsers()->count();

        $slotHourGroups = [];
        $pendingProposals = collect();
        $slotCardBadgeItemsByActivityId = [];
        $slotTypeBadgeItemsBySlotId = [];
        $proposalBadgeItemsByProposalId = [];
        $freeSlotsAllForProposals = collect();

        if ($this->tab === 'plan') {
            $syncEvent = Event::query()->whereKey($this->eventId)->firstOrFail();
            $syncEvent->load(['slots.activity']);
            app(SlotScheduleSyncService::class)->syncSlotEndsForEvent($syncEvent);

            $event->load([
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

            $slotHourGroups = $slotPresentation->slotHourGroupsForEvent($event);

            foreach ($event->slots as $slot) {
                $activity = $slot->activity;
                if ($activity !== null) {
                    $slotCardBadgeItemsByActivityId[(int) $activity->id] = $badgeGroupBuilder->build(
                        $activity,
                        ActivityBadgeGroupConfig::eventSlotCard(),
                    );
                } else {
                    $slotActivityTypes = collect($slot->activityTypes)
                        ->map(fn ($row) => $row->slug ? __('ui.activities.types.'.$row->slug) : null)
                        ->filter()
                        ->unique()
                        ->values();
                    if ($slotActivityTypes->isNotEmpty()) {
                        $slotTypeBadgeItemsBySlotId[(int) $slot->id] = $badgeGroupBuilder->buildActivityTypeChips(
                            $slotActivityTypes,
                            BadgeSemantic::Info,
                        );
                    }
                }
            }
        } elseif ($this->tab === 'proposals' && $canManageEvent && $hasPendingProposals) {
            $event->load([
                'slots' => fn ($q) => $q->with('activityTypes')->orderBy('starts_at'),
            ]);
            $freeSlotsAllForProposals = $event->slots->whereNull('activity_id')->values();

            $pendingProposals = $event->proposals()
                ->with(['activity.tags.translations', 'activity.tags.tagCategory', 'activity.activityType', 'creator', 'proposedSlots'])
                ->where('status', ActivityProposalStatus::Pending)
                ->orderBy('created_at')
                ->get();

            foreach ($pendingProposals as $proposal) {
                $pa = $proposal->activity;
                if ($pa !== null) {
                    $proposalBadgeItemsByProposalId[(int) $proposal->id] = $badgeGroupBuilder->build(
                        $pa,
                        ActivityBadgeGroupConfig::eventProposal(),
                    );
                }
            }
        }

        $previewActivity = $this->activityPreviewModalOpen && $this->previewActivityId !== null
            ? $this->previewActivityQuery($this->previewActivityId)
                ->withCount(['participants', 'waitlist'])
                ->first()
            : null;
        $previewActivityBadgeItems = [];
        $previewActivityParticipation = null;
        $previewActivityHasActiveEnrollmentWindow = false;
        if ($previewActivity !== null) {
            $previewActivity->loadMissing([
                'slot.event.enrollmentWindows',
                'slot.place.parent.city',
                'slot.place.city',
                'place.parent.city',
                'place.city',
                'creator',
                'canceller',
                'activityType',
                'tags.translations',
                'tags.tagCategory',
            ]);
            $previewActivityHasActiveEnrollmentWindow = $this->activityHasActiveEnrollmentWindow($previewActivity, $signupService);
            if ($this->normalizeActivityPreviewTab($this->activityPreviewTab) === 'participation'
                && $previewActivityHasActiveEnrollmentWindow) {
                $previewActivity->loadMissing([
                    'participants.user',
                    'waitlist.user',
                ]);
            }
            $previewActivityBadgeItems = $badgeGroupBuilder->build(
                $previewActivity,
                ActivityBadgeGroupConfig::activityHero(),
            );
            $previewActivityParticipation = $participationView->forShow($previewActivity, $user);
        } elseif ($this->activityPreviewModalOpen) {
            $this->closeActivityPreview();
        }

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

        $attachedActivityIds = $event->relationLoaded('slots')
            ? $event->slots
                ->pluck('activity_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
            : $eventActivityIds;

        return view('livewire.events.show-event', [
            'event' => $event,
            'attachedActivityIds' => $attachedActivityIds,
            'eventSignupPressureBlocksDelete' => $event->organiserHardDeleteBlockedWhileActive(),
            'activeEnrollmentWindow' => $activeEnrollmentWindow,
            'activeWindowRemainingByActivityId' => $activeWindowRemainingByActivityId,
            'pendingProposals' => $pendingProposals,
            'hasPendingProposals' => $hasPendingProposals,
            'canManageEvent' => $canManageEvent,
            'canShowPlanActivityProposalUi' => $canShowPlanActivityProposalUi,
            'showEmptySlots' => (bool) $this->showEmptySlots,
            'emptySlotsRestricted' => $shouldRestrictEmptySlots,
            'hasInterest' => $hasInterest,
            'interestedActivityIds' => $interestedActivityIds,
            'confirmedActivitiesCount' => $confirmedActivitiesCount,
            'confirmedParticipantsCount' => $confirmedParticipantsCount,
            'interestedPeopleCount' => $interestedPeopleCount,
            'previewActivity' => $previewActivity,
            'previewActivityBadgeItems' => $previewActivityBadgeItems,
            'previewActivityParticipation' => $previewActivityParticipation,
            'previewActivityHasActiveEnrollmentWindow' => $previewActivityHasActiveEnrollmentWindow,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotHourGroups' => $slotHourGroups,
            'slotCardBadgeItemsByActivityId' => $slotCardBadgeItemsByActivityId,
            'slotTypeBadgeItemsBySlotId' => $slotTypeBadgeItemsBySlotId,
            'proposalBadgeItemsByProposalId' => $proposalBadgeItemsByProposalId,
            'freeSlotsAllForProposals' => $freeSlotsAllForProposals,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function confirmedProgrammeStats(int $eventId): array
    {
        $activitiesBase = Activity::query()
            ->whereHas('slot', fn ($q) => $q->where('event_id', $eventId))
            ->whereNull('cancelled_at');

        $confirmedActivitiesCount = (clone $activitiesBase)->count();

        $confirmedParticipantsCount = (int) ActivityUser::query()
            ->whereNull('activity_user.deleted_at')
            ->whereIn('activity_id', $activitiesBase->clone()->select('activities.id'))
            ->count();

        return [$confirmedActivitiesCount, $confirmedParticipantsCount];
    }

    /**
     * @return list<int>
     */
    private function slotAttachmentActivityIdsForEvent(int $eventId): array
    {
        return Slot::query()
            ->where('event_id', $eventId)
            ->whereNotNull('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function shouldRestrictEmptySlots(Event $event, ?User $user): bool
    {
        $canManageEvent = $user !== null && $user->canModifyEntity($event);

        return ! $canManageEvent
            && (
                ($event->starts_at !== null && now()->gte($event->starts_at))
                || $event->enrollmentWindows->first(function ($w) {
                    return $w->starts_at !== null
                        && $w->ends_at !== null
                        && now()->between($w->starts_at, $w->ends_at);
                }) !== null
            );
    }

    private function applyShowEmptySlotsPolicy(Event $event, ?User $user): void
    {
        $shouldRestrict = $this->shouldRestrictEmptySlots($event, $user);
        if ($this->showEmptySlots === null || $this->emptySlotsRestricted !== $shouldRestrict) {
            $this->showEmptySlots = ! $shouldRestrict;
            $this->emptySlotsRestricted = $shouldRestrict;
        }
    }

    private function activityHasActiveEnrollmentWindow(Activity $activity, EventActivitySignupService $signupService): bool
    {
        if (! $activity->relationLoaded('slot')) {
            $activity->loadMissing('slot.event.enrollmentWindows');
        } elseif ($activity->slot !== null && ! $activity->slot->relationLoaded('event')) {
            $activity->slot->loadMissing('event.enrollmentWindows');
        }

        $previewActivityEvent = $activity->slot?->event;

        return $previewActivityEvent !== null
            && $signupService->firstPeriodContaining($previewActivityEvent, now()) !== null;
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['description', 'plan', 'proposals'], true) ? $value : 'description';
    }

    private function normalizeActivityPreviewTab(?string $value): string
    {
        return in_array($value, ['info', 'participation'], true) ? $value : 'info';
    }

    private function previewActivityQuery(int $activityId): Builder
    {
        return Activity::query()
            ->whereKey($activityId)
            ->where(function (Builder $query) {
                $query->whereHas('slot', fn ($q) => $q->where('event_id', $this->eventId))
                    ->orWhereHas(
                        'proposals',
                        fn ($q) => $q->where('event_id', $this->eventId)
                            ->where('status', ActivityProposalStatus::Pending),
                    );
            });
    }

    private function selectedPreviewActivityOrFail(): Activity
    {
        abort_unless($this->previewActivityId !== null, 404);

        return $this->previewActivityQuery($this->previewActivityId)->firstOrFail();
    }

    private function showPreviewParticipationTab(): void
    {
        $this->activityPreviewTab = 'participation';
        $this->activityPreviewRefreshTick++;
        $this->planCounterRefreshTick++;
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
