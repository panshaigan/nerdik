<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\EventActivitySignupService;
use Illuminate\Validation\ValidationException;
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
            'creator',
            'tags.translations',
            'participants.user',
            'waitlist.user',
            'slot.event.enrollmentWindows',
            'slot.place.parent',
        ]);

        $isParticipant = auth()->check() && $activity->participants()->where('user_id', auth()->id())->exists();
        $onWaitlist = auth()->check() && $activity->waitlist()->where('user_id', auth()->id())->exists();

        $signupGateOk = true;
        $signupBlockedMessage = null;
        if (auth()->check() && ! $isParticipant && ! $onWaitlist && $activity->slot?->event_id) {
            try {
                app(EventActivitySignupService::class)->assertCanSignup($activity, auth()->user());
            } catch (ValidationException $e) {
                $signupGateOk = false;
                $signupBlockedMessage = collect($e->errors())->flatten()->first();
            }
        }

        $canJoin = auth()->check() && ! $isParticipant && ! $onWaitlist && $signupGateOk;
        $isFull = $activity->max_participants !== null && $activity->participants()->count() >= $activity->max_participants;
        $inWishlist = auth()->check() && auth()->user()->wishlistActivities()->where('activities.id', $activity->id)->exists();
        $canManageActivity = auth()->user()?->canModifyEntity($activity) ?? false;

        return view('livewire.activities.show-activity', [
            'activity' => $activity,
            'isParticipant' => $isParticipant,
            'onWaitlist' => $onWaitlist,
            'canJoin' => $canJoin,
            'isFull' => $isFull,
            'inWishlist' => $inWishlist,
            'canManageActivity' => $canManageActivity,
            'signupBlockedMessage' => $signupBlockedMessage,
        ]);
    }
}
