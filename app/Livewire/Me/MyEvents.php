<?php

namespace App\Livewire\Me;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyEvents extends Component
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
        $query = Event::query()
            ->where('created_by', $userId)
            ->with([
                'organization',
                'creator',
                'places.country.translations',
                'places.city.translations',
                'slots.activity.activityType',
                'slots.activityTypes',
            ]);

        $term = trim($this->q);
        if ($term !== '') {
            $query->where('events.name', 'like', '%'.$term.'%');
        }

        $this->applyBrowseEventSort($query);

        $events = $query->paginate(self::PER_PAGE);

        $interestedEventIds = auth()->user()->interestedEvents()->pluck('events.id')->map(fn ($id) => (int) $id)->all();
        $participatingEventIds = DB::table('activity_user')
            ->join('slots', 'slots.activity_id', '=', 'activity_user.activity_id')
            ->whereNotNull('slots.event_id')
            ->where('activity_user.user_id', $userId)
            ->where('activity_user.is_absent', false)
            ->distinct()
            ->pluck('slots.event_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.me.my-events', [
            'events' => $events,
            'interestedEventIds' => $interestedEventIds,
            'participatingEventIds' => $participatingEventIds,
        ]);
    }
}
