<?php

namespace App\Livewire\Me;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Models\Activity;
use App\Models\ActivityUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyParticipatedActivities extends Component
{
    use WithBrowseListingSort;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $userId = auth()->id();
        $query = Activity::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId)->where('is_absent', false))
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

        return view('livewire.me.my-participated-activities', [
            'activities' => $activities,
            'interestedActivityIds' => $interestedActivityIds,
            'participatingActivityIds' => $participatingActivityIds,
        ]);
    }
}
