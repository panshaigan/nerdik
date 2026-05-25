<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Enums\ActivityProposalStatus;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Services\ActivityParticipationViewService;
use App\Services\CancellationNotificationDispatcher;
use App\Services\EventActivitySignupService;
use App\Services\EventProgrammeCancellationSyncService;
use App\Services\EventShowReadCache;
use App\Traits\AuthorizesOwnership;
use Carbon\Carbon;
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
 *
 * Default tab when `?tab=` is absent (see {@see self::resolveDefaultTab()}): pending proposals for organizers,
 * then plan during active enrollment or while the event is in progress, otherwise description.
 */
class ShowEvent extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithActivityPreviewModal;
    use WithUiConfirmModal;

    public int $eventId;

    /**
     * Active main panel tab; synchronized with `?tab=` on this component only (see {@see ShowEvent::$queryString}).
     */
    public string $tab = 'description';

    /**
     * Tab keys whose panel Livewire children stay mounted so switching tabs does not wipe rendered content during requests.
     *
     * @var list<string>
     */
    public array $mountedTabs = [];

    /** Query string binding for {@see ShowEvent::$tab}. Nested tab Livewire children do not declare their own `tab` URL binding. */
    protected array $queryString = [
        'tab' => ['except' => 'description'],
    ];

    /** Bumped when nested tabs mutate programme shell meta (e.g. proposals cleared). */
    public int $shellRefreshTick = 0;

    public ?string $eventCancelReason = null;

    /** True after the organizer hydrates the mass-create slots modal (data loaded once). */
    public bool $slotCreateModalReady = false;

    /** @var list<string> */
    public array $slotModalNameSuggestions = [];

    /** @var list<string> */
    public array $slotModalBaseNameSuggestions = [];

    /**
     * Room rows grouped by venue place id for the mass-create modal.
     *
     * @var array<int, list<array{id: int, name: string}>>
     */
    public array $slotModalRoomsByVenueId = [];

    public function mount(Event $event): void
    {
        $event->loadMissing('enrollmentWindows');
        $this->eventId = $event->id;

        if (! request()->has('tab')) {
            $this->tab = $this->resolveDefaultTab($event);
        } else {
            $this->tab = $this->normalizeTab($this->tab);
        }

        $this->mountedTabs = [$this->tab];
        $user = auth()->user();
        if ($this->tab === 'proposals' && ($user === null || ! $user->canModifyEntity($event))) {
            $this->tab = 'description';
        }
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
        if (! in_array($this->tab, $this->mountedTabs, true)) {
            $this->mountedTabs[] = $this->tab;
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
        app(EventShowReadCache::class)->forgetEventInterestedCount((int) $event->id);
        $this->success(__('ui.interests.added_event'));
    }

    public function removeInterest(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->interestedEvents()->detach($event->id);
        app(EventShowReadCache::class)->forgetEventInterestedCount((int) $event->id);
        $this->warning(__('ui.interests.removed_event'));
    }

    /**
     * Loads mass-create modal data once and opens the dialog (organizers only).
     */
    public function openSlotCreateModal(): void
    {
        $event = Event::query()->whereKey($this->eventId)->with('places')->firstOrFail();
        $user = auth()->user();
        abort_unless($user !== null && $user->canModifyEntity($event), 403);

        if (! $this->slotCreateModalReady) {
            $this->slotModalNameSuggestions = Slot::distinctNameSuggestionsForUser($user->id);
            $this->slotModalBaseNameSuggestions = Slot::baseNameSuggestionsForUser($user->id);

            $slotMassVenues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            $venueIds = $slotMassVenues->pluck('id');
            $this->slotModalRoomsByVenueId = [];
            if ($venueIds->isNotEmpty()) {
                $children = Place::query()
                    ->whereIn('parent_id', $venueIds)
                    ->where('type', 'room')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
                foreach ($slotMassVenues as $v) {
                    $this->slotModalRoomsByVenueId[$v->id] = ($children[$v->id] ?? collect())
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                        ->values()
                        ->all();
                }
            }

            $this->slotCreateModalReady = true;
        }

        $this->js('document.getElementById("event-slots-create-modal")?.showModal()');
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

        $hasPendingProposals = $canManageEvent && $eventShowReadCache->hasPendingProposals((int) $event->id);

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

        $interestedPeopleCount = $eventShowReadCache->eventInterestedCount((int) $event->id);

        $activityPreviewData = $this->resolveActivityPreviewViewData(
            $participationView,
            $badgeGroupBuilder,
            $signupService,
        );

        $slotNameSuggestions = [];
        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        $slotBaseNameSuggestions = [];
        if ($canManageEvent && $this->slotCreateModalReady) {
            $slotNameSuggestions = $this->slotModalNameSuggestions;
            $slotBaseNameSuggestions = $this->slotModalBaseNameSuggestions;
            $slotMassVenues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            $slotMassRoomsByVenueId = $this->slotModalRoomsByVenueId;
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
            ...$activityPreviewData,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotCreateModalReady' => $this->slotCreateModalReady,
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

    protected function previewActivityQuery(int $activityId): Builder
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

    protected function showPreviewParticipationActions(?Activity $activity): bool
    {
        if ($activity === null) {
            return false;
        }

        return (int) ($activity->slot?->event_id) === (int) $this->eventId;
    }

    protected function previewActivityBelongsToParticipationBroadcast(int $activityId): bool
    {
        return Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($query) => $query->where('event_id', $this->eventId))
            ->exists();
    }

    protected function afterPreviewParticipationChanged(): void
    {
        $this->dispatch('event-show-plan-counter-bump');
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['description', 'plan', 'proposals'], true) ? $value : 'description';
    }

    private function resolveDefaultTab(Event $event): string
    {
        $user = auth()->user();
        $eventShowReadCache = app(EventShowReadCache::class);

        if ($user !== null
            && $user->canModifyEntity($event)
            && $eventShowReadCache->hasPendingProposals((int) $event->id)) {
            return 'proposals';
        }

        $now = now();

        if ($this->hasActiveEnrollmentWindow($event, $now) || $this->eventIsInProgress($event, $now)) {
            return 'plan';
        }

        return 'description';
    }

    private function hasActiveEnrollmentWindow(Event $event, Carbon $now): bool
    {
        return $event->enrollmentWindows->contains(function ($window) use ($now): bool {
            return $window->starts_at !== null
                && $window->ends_at !== null
                && $now->between($window->starts_at, $window->ends_at);
        });
    }

    private function eventIsInProgress(Event $event, Carbon $now): bool
    {
        if ($event->starts_at === null) {
            return false;
        }

        if (! $now->gte($event->starts_at)) {
            return false;
        }

        if ($event->ends_at !== null && $now->gt($event->ends_at)) {
            return false;
        }

        return true;
    }
}
