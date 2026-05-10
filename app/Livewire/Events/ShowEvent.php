<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Enums\ActivityProposalStatus;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Services\ActivityParticipationService;
use App\Services\ActivityParticipationViewService;
use App\Services\CancellationNotificationDispatcher;
use App\Services\EventActivitySignupService;
use App\Services\EventProgrammeCancellationSyncService;
use App\Services\EventShowReadCache;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Event show shell: owns `tab` and the `?tab=` query string ({@see self::$tab}, {@see self::normalizeTab()}).
 * Nested tab components receive {@see ShowEvent::$tab} via the `active-tab` Blade prop only; they must not use
 * {@see Url} or read `tab` from the request for routing UI state.
 */
class ShowEvent extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithUiConfirmModal;

    public int $eventId;

    /**
     * Active main panel tab; synchronized with `?tab=` on this component only (see {@see ShowEvent::$queryString}).
     */
    public string $tab = 'description';

    /** Query string binding for {@see ShowEvent::$tab}. Nested tab Livewire children do not declare their own `tab` URL binding. */
    protected array $queryString = [
        'tab' => ['except' => 'description'],
    ];

    /** Bumped when nested tabs mutate programme shell meta (e.g. proposals cleared). */
    public int $shellRefreshTick = 0;

    public bool $activityPreviewModalOpen = false;

    public ?int $previewActivityId = null;

    public string $activityPreviewTab = 'info';

    /** Bumped when the selected activity preview receives a roster broadcast. */
    public int $activityPreviewRefreshTick = 0;

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
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
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

    #[On('open-event-activity-preview')]
    public function handleOpenEventActivityPreview(int $activityId): void
    {
        $this->openActivityPreview($activityId);
    }

    #[On('event-show-shell-refresh')]
    public function refreshShellFromNestedTabs(): void
    {
        $this->shellRefreshTick++;
    }

    #[On('event-proposal-submitted-broadcast')]
    public function refreshShellForIncomingProposalBroadcast(int|string $eventId): void
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

        $this->shellRefreshTick++;
    }

    #[On('event-plan-activity-participation-updated')]
    public function refreshPreviewFromParticipationBroadcast(int|string|null $activityId = null): void
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

        if ($this->activityPreviewModalOpen && (int) $this->previewActivityId === $activityId) {
            $this->activityPreviewRefreshTick++;
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

        $this->dispatch('slot-mutations-refresh');
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

        $this->dispatch('slot-mutations-refresh');
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

    public function runConfirmedAction(): void
    {
        $action = $this->pendingAction;
        $this->closeConfirm();

        if ($action === null) {
            return;
        }

        match ($action) {
            'delete_event' => $this->deleteEvent(),
            'cancel_event' => $this->cancelEvent(),
            'reopen_event' => $this->reopenEvent(),
            default => null,
        };
    }

    public function render(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
        EventShowReadCache $eventShowReadCache,
    ): View {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'creator',
            'canceller',
            'organization',
            'places',
            'enrollmentWindows',
        ]);

        $user = auth()->user();
        $canManageEvent = $user !== null && $user->canModifyEntity($event);

        $eventActivityIds = $this->slotAttachmentActivityIdsForEvent($event->id);
        [$confirmedActivitiesCount, $confirmedParticipantsCount] = $eventShowReadCache->programmeStats($event->id);

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

        return view('livewire.events.show-event', [
            'event' => $event,
            'attachedActivityIds' => $eventActivityIds,
            'eventSignupPressureBlocksDelete' => $event->organiserHardDeleteBlockedWhileActive(),
            'hasPendingProposals' => $hasPendingProposals,
            'canManageEvent' => $canManageEvent,
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
        ]);
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
        $this->dispatch('event-show-plan-counter-bump');
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
