<?php

namespace App\Livewire\Dashboard;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithEventPreviewModal;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Services\ActivityParticipationViewService;
use App\Services\Dashboard\UpcomingFeedQueryService;
use App\Services\EventActivitySignupService;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
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

    private const PER_PAGE = 15;

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
    ) {
        $user = Auth::user();
        $this->toastFromSessionStatus();

        $hostedActivitiesCount = Activity::query()
            ->where('created_by', $user->id)
            ->count();

        $hostedEventsCount = Event::query()
            ->where('created_by', $user->id)
            ->count();

        $participatedActivitiesCount = ActivityUser::query()
            ->where('user_id', $user->id)
            ->where('is_absent', false)
            ->distinct('activity_id')
            ->count('activity_id');

        $feed = $this->buildUnifiedUpcomingFeed($user->id);

        $eventIds = collect($feed->items())
            ->where('kind', 'event')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activityIds = collect($feed->items())
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

        $feedItems = collect($feed->items())
            ->map(function (array $row) use ($eventsById, $activitiesById) {
                if ($row['kind'] === 'event') {
                    $event = $eventsById->get($row['id']);

                    return $event ? ['kind' => 'event', 'event' => $event] : null;
                }

                $activity = $activitiesById->get($row['id']);

                return $activity ? ['kind' => 'activity', 'activity' => $activity] : null;
            })
            ->filter()
            ->values();

        $feed->setCollection($feedItems);

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
            'hostedActivitiesCount' => $hostedActivitiesCount,
            'hostedEventsCount' => $hostedEventsCount,
            'participatedActivitiesCount' => $participatedActivitiesCount,
            'feed' => $feed,
            'interestedEventIds' => $interestedEventIds,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
            'participatingEventIds' => $participatingEventIds,
            ...$this->resolveActivityPreviewViewData($participationView, $badgeGroupBuilder, $signupService),
            ...$this->resolveEventPreviewViewData($listingCardPresenter),
            'includeEventPreviewModal' => true,
        ]);
    }

    private function buildUnifiedUpcomingFeed(int $userId): LengthAwarePaginator
    {
        $service = app(UpcomingFeedQueryService::class);
        $rows = $service->buildUnifiedUpcomingRows($userId);
        $sorted = $service->dedupeAndSort($rows);

        $page = (int) request()->query('page', 1);
        $total = $sorted->count();
        $slice = $sorted->forPage($page, self::PER_PAGE)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function toastFromSessionStatus(): void
    {
        $status = session()->pull('status');
        if (is_string($status) && $status !== '') {
            $this->info($status);
        }
    }
}
