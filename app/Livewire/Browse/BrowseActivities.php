<?php

namespace App\Livewire\Browse;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Livewire\Concerns\WithActivityPreviewModal;
use App\Livewire\Concerns\WithBrowseListingSort;
use App\Livewire\Concerns\WithBrowseTagFilter;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Place;
use App\Models\Tag;
use App\Services\ActivityParticipationViewService;
use App\Services\EventActivitySignupService;
use App\Support\Browse\BrowseFullTextSearch;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class BrowseActivities extends Component
{
    use Toast;
    use WithActivityPreviewModal;
    use WithBrowseListingSort;
    use WithBrowseTagFilter;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $from_date = null;

    #[Url]
    public ?string $to_date = null;

    #[Url]
    public ?int $place_id = null;

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function clearTextSearch(): void
    {
        $this->q = '';
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function updatedPlaceId(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q', 'from_date', 'to_date', 'place_id']);
        $this->resetTagFilter();

        return $this->redirectRoute('search.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== ''
            || filled($this->from_date)
            || filled($this->to_date)
            || $this->place_id !== null
            || $this->hasTagFilterActive();
    }

    public function toggleActivityInterest(int $activityId): void
    {
        $activity = Activity::query()->whereKey($activityId)->firstOrFail();
        $user = auth()->user();
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
    ) {
        $query = Activity::with(Activity::listingCardEagerLoad())
            ->attachedToPublicEvent();

        if (filled($this->from_date)) {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                        ->whereDate('starts_at', '>=', $this->from_date);
                })->orWhere(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                        ->whereHas('slot', fn ($slotQ) => $slotQ->whereDate('starts_at', '>=', $this->from_date));
                });
            });
        }
        if (filled($this->to_date)) {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                        ->whereDate('starts_at', '<=', $this->to_date);
                })->orWhere(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                        ->whereHas('slot', fn ($slotQ) => $slotQ->whereDate('starts_at', '<=', $this->to_date));
                });
            });
        }
        if ($this->place_id !== null) {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                        ->where('activities.place_id', $this->place_id);
                })->orWhere(function ($sq) {
                    $sq->where('hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                        ->whereHas('slot.place', fn ($slotQ) => $slotQ->where('places.id', $this->place_id));
                });
            });
        }

        $this->applyBrowseTagFilter($query, 'tags');

        BrowseFullTextSearch::applyActivityHybrid($query, $this->q);

        $this->applyBrowseActivitySort($query);

        $activities = $query->paginate(self::PER_PAGE);

        $places = Place::orderBy('name')->get();

        $interestedActivityIds = auth()->check()
            ? auth()->user()->interestedActivities()->pluck('activities.id')->toArray()
            : [];
        $participatingActivityIds = auth()->check()
            ? ActivityUser::query()
                ->where('user_id', auth()->id())
                ->where('is_absent', false)
                ->distinct('activity_id')
                ->pluck('activity_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        return view('livewire.browse.browse-activities', [
            'activities' => $activities,
            'places' => $places,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
            'tags' => Tag::query()->forBrowseSelector($this->tag_ids),
            ...$this->resolveActivityPreviewViewData($participationView, $badgeGroupBuilder, $signupService),
            'includeEventPreviewModal' => false,
        ]);
    }
}
