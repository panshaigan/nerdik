<?php

namespace App\Livewire\Events;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithEventPreviewModal;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Livewire\EntityLinks\ManageEntityLinks;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\User;
use App\Services\ActivityParticipationViewService;
use App\Services\ActivityPeopleStats;
use App\Services\EventActivitySignupService;
use App\Services\EventShowReadCache;
use App\Services\UserInterestService;
use App\Support\Calendar\CalendarLinks;
use App\Support\Sharing\ShareLinks;
use App\Support\Ui\BrowseListingCardPresenter;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowEventSeries extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithActivityPreviewModal;
    use WithEventPreviewModal;
    use WithUiConfirmModal;

    public int $eventSeriesId;

    public string $tab = 'events';

    protected array $queryString = [
        'tab' => ['except' => 'events'],
    ];

    public function mount(EventSeries $eventSeries): void
    {
        abort_unless($eventSeries->isVisibleTo(auth()->user()), 404);

        $this->eventSeriesId = $eventSeries->id;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    public function toggleEventInterest(int $eventId, UserInterestService $interests): void
    {
        $event = $this->seriesEventsQuery()->whereKey($eventId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $added = $interests->toggleEventInterest($user, $event);
        if ($added) {
            $this->success(__('ui.interests.added_event'));
        } else {
            $this->warning(__('ui.interests.removed_event'));
        }
    }

    public function toggleActivityInterest(int $activityId, UserInterestService $interests): void
    {
        $activity = $this->previewActivityQuery($activityId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $added = $interests->toggleActivityInterest($user, $activity);
        if ($added) {
            $this->success(__('ui.interests.added_activity'));
        } else {
            $this->warning(__('ui.interests.removed_activity'));
        }
    }

    public function toggleUpcomingInterest(UserInterestService $interests): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $upcoming = $this->upcomingEventsForFollow();
        abort_if($upcoming->isEmpty(), 403);

        $added = $interests->toggleUpcomingEventInterests($user, $upcoming);
        if ($added) {
            $this->success(__('ui.event_series.followed_upcoming'));
        } else {
            $this->warning(__('ui.event_series.unfollowed_upcoming'));
        }
    }

    public function confirmDeleteSeries(): void
    {
        $this->openConfirm(
            'delete_series',
            __('ui.common.delete'),
            __('ui.event_series.delete_confirm'),
        );
    }

    public function openAddEntityLink(): void
    {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null && $user->canManageEntityLinks($series), 403);

        $this->dispatch(
            'open-add-entity-link',
            key: $series->getMorphClass().'-'.$series->id,
        )->to(ManageEntityLinks::class);
    }

    public function runConfirmedAction(): void
    {
        $action = $this->pendingAction;
        $this->closeConfirm();

        if ($action === null) {
            return;
        }

        match ($action) {
            'delete_series' => $this->deleteSeries(),
            default => null,
        };
    }

    public function deleteSeries(): void
    {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        $this->authorizeCreatedBy($series);

        Event::query()
            ->where('event_series_id', $series->id)
            ->update(['event_series_id' => null]);

        $series->delete();
        $this->success(__('ui.event_series.deleted_status'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function render(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
        BrowseListingCardPresenter $listingCardPresenter,
        EventShowReadCache $eventShowReadCache,
        ActivityPeopleStats $activityPeopleStats,
        ShareLinks $shareLinks,
        CalendarLinks $calendarLinks,
    ): View {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);
        $series->load('links');

        /** @var Collection<int, Event> $events */
        $events = $this->seriesEventsQuery()
            ->with(Event::listingCardEagerLoad())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        $activities = $this->seriesActivities($events);
        $hosts = $this->uniqueActivityHosts($activities);
        $stats = $this->seriesStats($events, $activities, $activityPeopleStats);

        $eventStatsById = [];
        foreach ($events as $event) {
            [$confirmedActivities, $confirmedParticipants, $availablePlaces] = $eventShowReadCache->programmeStats((int) $event->id);
            $eventStatsById[(int) $event->id] = [
                'confirmed_activities' => $confirmedActivities,
                'confirmed_participants' => $confirmedParticipants,
                'available_places_label' => $availablePlaces === null ? '∞' : (string) $availablePlaces,
                'interested_people_count' => $eventShowReadCache->eventInterestedCount((int) $event->id),
            ];
        }

        $user = Auth::user();
        $interestedEventIds = $user !== null
            ? $user->interestedEvents()
                ->whereIn('events.id', $events->pluck('id'))
                ->pluck('events.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        $interestedActivityIds = $user !== null
            ? $user->interestedActivities()
                ->whereIn('activities.id', $activities->pluck('id'))
                ->pluck('activities.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $upcomingEvents = $series->visibleUpcomingEvents($user);
        $upcomingEventIds = $upcomingEvents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hasUpcomingFollow = $upcomingEventIds !== [];
        $hasUpcomingInterest = $hasUpcomingFollow
            && array_diff($upcomingEventIds, $interestedEventIds) === [];

        return view('livewire.events.show-event-series', [
            'series' => $series,
            'events' => $events,
            'hosts' => $hosts,
            'activities' => $activities,
            'stats' => $stats,
            'eventStatsById' => $eventStatsById,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'browsingReturnUrl' => route('event-series.show', $series),
            'sharePayload' => $shareLinks->forEventSeries($series),
            'calendarPayload' => $calendarLinks->forEventSeries($series, $upcomingEvents),
            'canManageSeries' => $user !== null && $user->canModifyEntity($series),
            'latestEvent' => $events
                ->sortBy([
                    ['starts_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first(),
            'hasUpcomingFollow' => $hasUpcomingFollow,
            'hasUpcomingInterest' => $hasUpcomingInterest,
            ...$this->resolveActivityPreviewViewData($participationView, $badgeGroupBuilder, $signupService),
            ...$this->resolveEventPreviewViewData($listingCardPresenter),
            'includeEventPreviewModal' => true,
        ]);
    }

    /**
     * @return Builder<Event>
     */
    private function seriesEventsQuery(): Builder
    {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        $viewer = auth()->user();

        $query = Event::query()->where('event_series_id', $this->eventSeriesId);

        if ($viewer === null || (int) $viewer->id !== (int) $series->created_by) {
            $query->where('is_public', true);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return list<array{user: User, label: string}>
     */
    private function uniqueActivityHosts(Collection $activities): array
    {
        $hosts = [];

        foreach ($activities as $activity) {
            $creator = $activity->creator;
            if ($creator === null) {
                continue;
            }

            $hosts[(int) $creator->id] = [
                'user' => $creator,
                'label' => $creator->displayName(),
            ];
        }

        return array_values($hosts);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Activity>
     */
    private function seriesActivities(Collection $events): Collection
    {
        $eventIds = $events->pluck('id')->all();
        if ($eventIds === []) {
            return collect();
        }

        return Activity::query()
            ->whereHas('slot', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->with(Activity::listingCardEagerLoad())
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Activity>  $activities
     * @return array{
     *   editions_count: int,
     *   upcoming_count: int,
     *   past_count: int,
     *   cancelled_count: int,
     *   activities_count: int,
     *   participants_total: int,
     *   participants_unique: int
     * }
     */
    private function seriesStats(Collection $events, Collection $activities, ActivityPeopleStats $activityPeopleStats): array
    {
        $now = now();
        $upcoming = $events->filter(fn (Event $e) => ! $e->isCancelled() && $e->starts_at !== null && $e->starts_at->gte($now))->count();
        $past = $events->filter(fn (Event $e) => ! $e->isCancelled() && $e->ends_at !== null && $e->ends_at->lt($now))->count();
        $cancelled = $events->filter(fn (Event $e) => $e->isCancelled())->count();

        $activityIds = $activities->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $participantsTotal = $activityPeopleStats->totalIncludingHosts($activityIds, excludeAbsent: true);
        $participantsUnique = $activityPeopleStats->uniqueIncludingHosts($activityIds, excludeAbsent: true);

        return [
            'editions_count' => $events->count(),
            'upcoming_count' => $upcoming,
            'past_count' => $past,
            'cancelled_count' => $cancelled,
            'activities_count' => $activities->count(),
            'participants_total' => $participantsTotal,
            'participants_unique' => $participantsUnique,
        ];
    }

    protected function previewEventQuery(int $eventId): Builder
    {
        return $this->seriesEventsQuery()
            ->whereKey($eventId)
            ->with(Event::listingCardEagerLoad());
    }

    protected function previewActivityQuery(int $activityId): Builder
    {
        $eventIds = $this->seriesEventsQuery()->pluck('id')->all();

        return Activity::query()
            ->whereKey($activityId)
            ->whereHas('slot', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->with(Activity::listingCardEagerLoad());
    }

    protected function useListingCardLocationInActivityPreview(): bool
    {
        return true;
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['events', 'hosts', 'activities', 'stats'], true)
            ? $value
            : 'events';
    }

    /**
     * @return Collection<int, Event>
     */
    private function upcomingEventsForFollow(): Collection
    {
        $series = EventSeries::query()->whereKey($this->eventSeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);

        return $series->visibleUpcomingEvents(auth()->user());
    }
}
