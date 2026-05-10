<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Enums\BadgeSemantic;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityProposalDecisionService;
use App\Services\EventSlotPresentationService;
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
 * Programme / slots panel for {@see ShowEvent}. Mounted only when the shell `tab` is `plan`.
 * Tab selection and `?tab=` live on the parent; do not bind `tab` to the query string here.
 */
#[Lazy]
class EventShowPlanTab extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithUiConfirmModal;

    public int $eventId;

    /**
     * Mirrors {@see ShowEvent::$tab} from the shell; for debugging/contracts — not read from the request URL.
     */
    public string $activeTab = 'plan';

    /** Free slots marked for “Propose an activity” preferred slots (slot ids). */
    public array $proposalSlotIds = [];

    /** Controls whether slots without attached activity are visible on the plan tab. */
    public ?bool $showEmptySlots = null;

    /** Tracks whether empty-slot auto-hide rule is currently active for this viewer. */
    public bool $emptySlotsRestricted = false;

    /** Bumped when slots change via async JS so the component re-renders. */
    public int $slotListVersion = 0;

    /** Bumped when activity roster changes should refresh plan tab counters. */
    public int $planCounterRefreshTick = 0;

    /** @var array<int|string, string> */
    public array $slotCancelReason = [];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="flex min-h-[16rem] items-center justify-center p-8" data-ui="event-show-plan-tab-placeholder">
            <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
        </div>
        HTML;
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $event = Event::query()->whereKey($this->eventId)->with('enrollmentWindows')->firstOrFail();
        $this->applyShowEmptySlotsPolicy($event, auth()->user());
    }

    /**
     * Opens the activity preview modal on the parent {@see ShowEvent} shell.
     */
    public function openActivityPreview(int $activityId): void
    {
        $this->dispatch('open-event-activity-preview', activityId: $activityId);
    }

    #[On('event-show-plan-counter-bump')]
    public function bumpPlanCounterFromParentPreview(): void
    {
        $this->planCounterRefreshTick++;
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

        $this->planCounterRefreshTick++;
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
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
    ): View {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'places',
            'enrollmentWindows',
        ]);

        $enrollment = $slotPresentation->enrollmentPresentation($event, now());
        $activeEnrollmentWindow = $enrollment->activeWindow;
        $activeWindowRemainingByActivityId = $enrollment->remainingByActivityId;

        $user = auth()->user();
        /**
         * Exposed for {@see resources/views/livewire/events/partials/show-plan-tab.blade.php}.
         */
        $canManageEvent = $user !== null && $user->canModifyEntity($event);
        $canShowPlanActivityProposalUi = $user !== null
            && ! $event->isCancelled()
            && ($event->starts_at === null || now()->lt($event->starts_at))
            && $activeEnrollmentWindow === null;

        $this->applyShowEmptySlotsPolicy($event, $user);

        $eventActivityIds = Slot::query()
            ->where('event_id', $event->id)
            ->whereNotNull('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $interestedActivityIds = $user !== null && $eventActivityIds !== []
            ? $user->interestedActivities()
                ->whereIn('activities.id', $eventActivityIds)
                ->pluck('activities.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

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

        $slotCardBadgeItemsByActivityId = [];
        $slotTypeBadgeItemsBySlotId = [];
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

        return view('livewire.events.event-show-plan-tab', [
            'event' => $event,
            'canManageEvent' => $canManageEvent,
            'activeEnrollmentWindow' => $activeEnrollmentWindow,
            'activeWindowRemainingByActivityId' => $activeWindowRemainingByActivityId,
            'canShowPlanActivityProposalUi' => $canShowPlanActivityProposalUi,
            'showEmptySlots' => (bool) $this->showEmptySlots,
            'emptySlotsRestricted' => $this->shouldRestrictEmptySlots($event, $user),
            'interestedActivityIds' => $interestedActivityIds,
            'slotHourGroups' => $slotHourGroups,
            'slotCardBadgeItemsByActivityId' => $slotCardBadgeItemsByActivityId,
            'slotTypeBadgeItemsBySlotId' => $slotTypeBadgeItemsBySlotId,
        ]);
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
}
