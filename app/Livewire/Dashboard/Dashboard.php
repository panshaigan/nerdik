<?php

namespace App\Livewire\Dashboard;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithEventPreviewModal;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Services\ActivityParticipationViewService;
use App\Services\Dashboard\DashboardFeedPresentationService;
use App\Services\Dashboard\UpcomingFeedQueryService;
use App\Services\EventActivitySignupService;
use App\Support\Ui\BrowseListingCardPresenter;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Dashboard extends Component
{
    use Toast;
    use WithActivityPreviewModal;
    use WithEventPreviewModal;
    use WithPagination;

    private const GROUPS_PER_PAGE = 8;

    public function toggleEventInterest(int $eventId): void
    {
        $event = Event::query()->whereKey($eventId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $alreadyInterested = $user->interestedEvents()->whereKey($event->id)->exists();
        if ($alreadyInterested) {
            $user->interestedEvents()->detach($event->id);
            $this->warning(__('ui.interests.removed_event'));

            return;
        }

        $user->interestedEvents()->syncWithoutDetaching([$event->id]);
        $this->success(__('ui.interests.added_event'));
    }

    public function toggleActivityInterest(int $activityId): void
    {
        $activity = Activity::query()->whereKey($activityId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $alreadyInterested = $user->interestedActivities()->whereKey($activity->id)->exists();
        if ($alreadyInterested) {
            $user->interestedActivities()->detach($activity->id);
            $this->warning(__('ui.interests.removed_activity'));

            return;
        }

        $user->interestedActivities()->syncWithoutDetaching([$activity->id]);
        $eventId = $activity->slot?->event_id;
        if ($eventId !== null) {
            $user->interestedEvents()->syncWithoutDetaching([(int) $eventId]);
        }
        $this->success(__('ui.interests.added_activity'));
    }

    public function render(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
        BrowseListingCardPresenter $listingCardPresenter,
        DashboardFeedPresentationService $feedPresentation,
        UpcomingFeedQueryService $upcomingFeedQuery,
    ) {
        $user = Auth::user();
        $this->toastFromSessionStatus();

        $upcomingActivityStats = $upcomingFeedQuery->upcomingActivityStatsForUser($user->id);

        $sortedRows = $this->buildSortedUpcomingRows($user->id);

        $eventIds = $sortedRows
            ->where('kind', 'event')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activityIds = $sortedRows
            ->where('kind', 'activity')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $eventsById = $eventIds === []
            ? collect()
            : Event::query()
                ->with(Event::listingCardEagerLoad())
                ->whereIn('id', $eventIds)
                ->get()
                ->keyBy('id');

        $activitiesById = $activityIds === []
            ? collect()
            : Activity::query()
                ->with(Activity::listingCardEagerLoad())
                ->withCount(['participants as participants_count' => fn ($q) => $q->where('is_absent', false)])
                ->whereIn('id', $activityIds)
                ->get()
                ->keyBy('id');

        $feedItems = $sortedRows
            ->map(function (array $row) use ($eventsById, $activitiesById) {
                if ($row['kind'] === 'event') {
                    $event = $eventsById->get($row['id']);

                    return $event ? [
                        'kind' => 'event',
                        'event' => $event,
                        'starts_at' => $this->startsAtForEvent($event),
                    ] : null;
                }

                $activity = $activitiesById->get($row['id']);

                return $activity ? [
                    'kind' => 'activity',
                    'activity' => $activity,
                    'starts_at' => $this->startsAtForActivity($activity),
                ] : null;
            })
            ->filter()
            ->values();

        $feedHourGroups = $this->paginateHourGroups(
            $feedPresentation->hourGroupsForFeedItems($feedItems),
        );

        $interestedEventIds = $user->interestedEvents()->pluck('events.id')->map(fn ($id) => (int) $id)->all();
        $interestedActivityIds = $user->interestedActivities()->pluck('activities.id')->map(fn ($id) => (int) $id)->all();
        $participatingActivityIds = ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('is_absent', false)
            ->distinct('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $participatingEventIds = DB::table('activity_user')
            ->join('slots', 'slots.activity_id', '=', 'activity_user.activity_id')
            ->whereNotNull('slots.event_id')
            ->where('activity_user.user_id', $user->id)
            ->where('activity_user.is_absent', false)
            ->distinct()
            ->pluck('slots.event_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $browsingReturnUrl = browsing_return_url();
        remember_browsing_return_url($browsingReturnUrl);

        return view('livewire.dashboard.dashboard', [
            'browsingReturnUrl' => $browsingReturnUrl,
            'upcomingInterestedActivitiesCount' => $upcomingActivityStats['interested'],
            'upcomingParticipatingActivitiesCount' => $upcomingActivityStats['participating'],
            'upcomingCreatedActivitiesCount' => $upcomingActivityStats['created'],
            'feedHourGroups' => $feedHourGroups,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
            'participatingEventIds' => $participatingEventIds,
            ...$this->resolveActivityPreviewViewData($participationView, $badgeGroupBuilder, $signupService),
            ...$this->resolveEventPreviewViewData($listingCardPresenter),
            'includeEventPreviewModal' => true,
        ]);
    }

    /**
     * @return Collection<int, array{kind: string, id: int, sort_at: mixed}>
     */
    private function buildSortedUpcomingRows(int $userId): Collection
    {
        $service = app(UpcomingFeedQueryService::class);

        return $service->dedupeAndSort($service->buildUnifiedUpcomingRows($userId));
    }

    /**
     * @param  list<array{label: string, items: Collection, starts_at: ?Carbon}>  $hourGroups
     */
    private function paginateHourGroups(array $hourGroups): LengthAwarePaginator
    {
        $page = (int) request()->query('page', 1);
        $total = count($hourGroups);
        $slice = collect($hourGroups)->forPage($page, self::GROUPS_PER_PAGE)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            self::GROUPS_PER_PAGE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function startsAtForEvent(Event $event): ?Carbon
    {
        return $event->starts_at ?? $event->ends_at;
    }

    private function startsAtForActivity(Activity $activity): ?Carbon
    {
        return $activity->slot?->starts_at ?? $activity->starts_at ?? $activity->ends_at;
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
