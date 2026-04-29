<?php

namespace App\Livewire\Me;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Models\Activity;
use App\Models\ActivityUser;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class MyActivities extends Component
{
    use Toast;
    use WithBrowseListingSort;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
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

    public function render()
    {
        $userId = auth()->id();
        $query = Activity::query()
            ->where('created_by', $userId)
            ->with(['creator', 'activityType', 'tags.translations', 'tags.tagCategory', 'slot.event', 'slot.place', 'place'])
            ->withCount(['participants as participants_count' => fn ($q) => $q->where('is_absent', false)]);

        $term = trim($this->q);
        if ($term !== '') {
            $query->where('activities.name', 'like', '%'.$term.'%');
        }

        $this->applyBrowseActivitySort($query);

        $activities = $query->paginate(self::PER_PAGE);

        $interestedActivityIds = auth()->user()->interestedActivities()->pluck('activities.id')->map(fn ($id) => (int) $id)->all();
        $participatingActivityIds = ActivityUser::query()
            ->where('user_id', $userId)
            ->where('is_absent', false)
            ->distinct('activity_id')
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.me.my-activities', [
            'activities' => $activities,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
        ]);
    }
}
