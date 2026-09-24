<?php

namespace App\Livewire\Activities;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithUiConfirmModal;
use App\Livewire\EntityLinks\ManageEntityLinks;
use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\User;
use App\Services\ActivityParticipationViewService;
use App\Services\ActivityPeopleStats;
use App\Services\EventActivitySignupService;
use App\Services\UserInterestService;
use App\Support\Calendar\CalendarLinks;
use App\Support\Sharing\ShareLinks;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class ShowActivitySeries extends Component
{
    use AuthorizesOwnership;
    use Toast;
    use WithActivityPreviewModal;
    use WithUiConfirmModal;

    #[Locked]
    public int $activitySeriesId;

    public string $tab = 'activities';

    public bool $editSeriesModalOpen = false;

    public string $editSeriesName = '';

    public ?string $editSeriesDescription = null;

    protected array $queryString = [
        'tab' => ['except' => 'activities'],
    ];

    public function hydrate(): void
    {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);
    }

    public function mount(ActivitySeries $activitySeries): void
    {
        abort_unless($activitySeries->isVisibleTo(auth()->user()), 404);

        $this->activitySeriesId = $activitySeries->id;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    public function toggleActivityInterest(int $activityId, UserInterestService $interests): void
    {
        $activity = $this->seriesActivitiesQuery()->whereKey($activityId)->firstOrFail();
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

        $upcoming = $this->upcomingActivitiesForFollow();
        abort_if($upcoming->isEmpty(), 403);

        $added = $interests->toggleUpcomingActivityInterests($user, $upcoming);
        if ($added) {
            $this->success(__('ui.activity_series.followed_upcoming'));
        } else {
            $this->warning(__('ui.activity_series.unfollowed_upcoming'));
        }
    }

    public function confirmDeleteSeries(): void
    {
        $this->openConfirm(
            'delete_series',
            __('ui.common.delete'),
            __('ui.activity_series.delete_confirm'),
        );
    }

    public function openAddEntityLink(): void
    {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user !== null && $user->canManageEntityLinks($series), 403);

        $this->dispatch(
            'open-add-entity-link',
            key: $series->getMorphClass().'-'.$series->id,
        )->to(ManageEntityLinks::class);
    }

    public function openEditSeries(): void
    {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        $this->authorizeCreatedBy($series);

        $this->editSeriesName = (string) $series->name;
        $this->editSeriesDescription = $series->description;
        $this->resetValidation();
        $this->editSeriesModalOpen = true;
    }

    public function saveSeries(): void
    {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        $this->authorizeCreatedBy($series);

        $validated = $this->validate([
            'editSeriesName' => ['required', 'string', 'max:255'],
            'editSeriesDescription' => ['nullable', 'string'],
        ], [], [
            'editSeriesName' => __('ui.activity_series.name'),
            'editSeriesDescription' => __('ui.activity_series.description'),
        ]);

        $previousSlug = $series->slug;

        $series->update([
            'name' => $validated['editSeriesName'],
            'description' => $validated['editSeriesDescription'] !== null && trim($validated['editSeriesDescription']) !== ''
                ? $validated['editSeriesDescription']
                : null,
        ]);

        $series->refresh();
        $this->editSeriesModalOpen = false;
        $this->success(__('ui.activity_series.updated_status'));

        if ($series->slug !== $previousSlug) {
            $this->redirect(route('activity-series.show', $series), navigate: true);
        }
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
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        $this->authorizeCreatedBy($series);

        Activity::query()
            ->where('activity_series_id', $series->id)
            ->update(['activity_series_id' => null]);

        $series->delete();
        $this->success(__('ui.activity_series.deleted_status'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function render(
        ActivityParticipationViewService $participationView,
        ActivityBadgeGroupBuilder $badgeGroupBuilder,
        EventActivitySignupService $signupService,
        ActivityPeopleStats $activityPeopleStats,
        ShareLinks $shareLinks,
        CalendarLinks $calendarLinks,
    ): View {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);
        $series->load('links');

        /** @var Collection<int, Activity> $activities */
        $activities = $this->seriesActivitiesQuery()
            ->with(Activity::listingCardEagerLoad())
            ->orderByRaw(Activity::scheduleStartsAtSql().' ASC')
            ->orderBy('activities.id')
            ->get();

        $hosts = $this->uniqueActivityHosts($activities);
        $stats = $this->seriesStats($activities, $activityPeopleStats);

        $user = Auth::user();
        $interestedActivityIds = $user !== null
            ? $user->interestedActivities()
                ->whereIn('activities.id', $activities->pluck('id'))
                ->pluck('activities.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $upcomingActivities = $series->visibleUpcomingActivities($user);
        $upcomingActivityIds = $upcomingActivities->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hasUpcomingFollow = $upcomingActivityIds !== [];
        $hasUpcomingInterest = $hasUpcomingFollow
            && array_diff($upcomingActivityIds, $interestedActivityIds) === [];
        $upcomingInterestedPeopleCount = $upcomingActivityIds === []
            ? 0
            : (int) Activity::query()
                ->whereIn('activities.id', $upcomingActivityIds)
                ->withCount('interestedUsers')
                ->get()
                ->sum('interested_users_count');

        $startsAtSql = Activity::scheduleStartsAtSql();
        $latestActivity = Activity::query()
            ->where('activity_series_id', $this->activitySeriesId)
            ->orderByRaw("{$startsAtSql} DESC")
            ->orderByDesc('activities.id')
            ->first();

        return view('livewire.activities.show-activity-series', [
            'series' => $series,
            'hosts' => $hosts,
            'activities' => $activities,
            'stats' => $stats,
            'interestedActivityIds' => $interestedActivityIds,
            'browsingReturnUrl' => route('activity-series.show', $series),
            'sharePayload' => $shareLinks->forActivitySeries($series),
            'calendarPayload' => $calendarLinks->forActivitySeries($series, $upcomingActivities),
            'canManageSeries' => $user !== null && $user->canModifyEntity($series),
            'latestActivity' => $latestActivity,
            'hasUpcomingFollow' => $hasUpcomingFollow,
            'hasUpcomingInterest' => $hasUpcomingInterest,
            'upcomingInterestedPeopleCount' => $upcomingInterestedPeopleCount,
            ...$this->resolveActivityPreviewViewData($participationView, $badgeGroupBuilder, $signupService),
            'includeEventPreviewModal' => false,
        ]);
    }

    /**
     * @return Builder<Activity>
     */
    private function seriesActivitiesQuery(): Builder
    {
        return Activity::query()
            ->where('activity_series_id', $this->activitySeriesId)
            ->visibleTo(auth()->user());
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
     * @param  Collection<int, Activity>  $activities
     * @return array{
     *   sessions_count: int,
     *   upcoming_count: int,
     *   past_count: int,
     *   cancelled_count: int,
     *   participants_total: int,
     *   participants_unique: int
     * }
     */
    private function seriesStats(Collection $activities, ActivityPeopleStats $activityPeopleStats): array
    {
        $now = now();
        $upcoming = $activities->filter(function (Activity $activity) use ($now): bool {
            if ($activity->isCancelled()) {
                return false;
            }
            $startsAt = $activity->scheduleStartsAt();

            return $startsAt !== null && $startsAt->gte($now);
        })->count();
        $past = $activities->filter(function (Activity $activity) use ($now): bool {
            if ($activity->isCancelled()) {
                return false;
            }
            $endsAt = $activity->slot?->ends_at ?? $activity->ends_at;
            if ($endsAt !== null) {
                return $endsAt->lt($now);
            }
            $startsAt = $activity->scheduleStartsAt();

            return $startsAt !== null && $startsAt->lt($now);
        })->count();
        $cancelled = $activities->filter(fn (Activity $a) => $a->isCancelled())->count();

        $activityIds = $activities->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $participantsTotal = $activityPeopleStats->totalIncludingHosts($activityIds, excludeAbsent: true);
        $participantsUnique = $activityPeopleStats->uniqueIncludingHosts($activityIds, excludeAbsent: true);

        return [
            'sessions_count' => $activities->count(),
            'upcoming_count' => $upcoming,
            'past_count' => $past,
            'cancelled_count' => $cancelled,
            'participants_total' => $participantsTotal,
            'participants_unique' => $participantsUnique,
        ];
    }

    protected function previewActivityQuery(int $activityId): Builder
    {
        return $this->seriesActivitiesQuery()
            ->whereKey($activityId)
            ->with(Activity::listingCardEagerLoad());
    }

    protected function useListingCardLocationInActivityPreview(): bool
    {
        return true;
    }

    private function normalizeTab(?string $value): string
    {
        return in_array($value, ['activities', 'hosts', 'stats'], true)
            ? $value
            : 'activities';
    }

    /**
     * @return Collection<int, Activity>
     */
    private function upcomingActivitiesForFollow(): Collection
    {
        $series = ActivitySeries::query()->whereKey($this->activitySeriesId)->firstOrFail();
        abort_unless($series->isVisibleTo(auth()->user()), 404);

        return $series->visibleUpcomingActivities(auth()->user());
    }
}
