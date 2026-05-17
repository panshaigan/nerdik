<?php

namespace App\Livewire\Me;

use App\Livewire\Concerns\WithBrowseListingSort;
use App\Livewire\Concerns\WithEventPreviewModal;
use App\Models\Event;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class MyEvents extends Component
{
    use Toast;
    use WithBrowseListingSort;
    use WithEventPreviewModal;
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url]
    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function toggleEventInterest(int $eventId): void
    {
        $event = Event::query()->whereKey($eventId)->firstOrFail();
        $user = auth()->user();
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

    public function render(BrowseListingCardPresenter $listingCardPresenter): mixed
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
            ...$this->resolveEventPreviewViewData($listingCardPresenter),
            'includeEventPreviewModal' => true,
            'includeActivityPreviewModal' => false,
        ]);
    }
}
