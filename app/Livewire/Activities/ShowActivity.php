<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use Livewire\Component;

class ShowActivity extends Component
{
    public int $activityId;

    public function mount(Activity $activity): void
    {
        $this->activityId = $activity->id;
    }

    public function render()
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();

        $activity->load([
            'host',
            'creator',
            'tags.translations',
            'participants.user',
            'waitlist.user',
            'slot.event',
            'slot.place.parent',
        ]);

        $isParticipant = auth()->check() && $activity->participants()->where('user_id', auth()->id())->exists();
        $onWaitlist = auth()->check() && $activity->waitlist()->where('user_id', auth()->id())->exists();
        $canJoin = auth()->check() && ! $isParticipant && ! $onWaitlist;
        $isFull = $activity->max_participants !== null && $activity->participants()->count() >= $activity->max_participants;
        $isHost = auth()->check() && $activity->host_user_id === auth()->id();
        $inWishlist = auth()->check() && auth()->user()->wishlistActivities()->where('activities.id', $activity->id)->exists();

        return view('livewire.activities.show-activity', [
            'activity' => $activity,
            'isParticipant' => $isParticipant,
            'onWaitlist' => $onWaitlist,
            'canJoin' => $canJoin,
            'isFull' => $isFull,
            'isHost' => $isHost,
            'inWishlist' => $inWishlist,
        ]);
    }
}
