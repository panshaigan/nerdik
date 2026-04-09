<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\ActivityHostingModeService;
use App\Services\EventActivitySignupService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ShowActivity extends Component
{
    public int $activityId;

    public ?string $cancelReason = null;

    public function mount(Activity $activity): void
    {
        $this->activityId = $activity->id;
    }

    public function cancel(ActivityHostingModeService $hostingModes): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($activity), 403);

        $reason = $this->cancelReason !== null ? trim($this->cancelReason) : null;
        if ($reason === '') {
            $reason = null;
        }
        if ($reason !== null && mb_strlen($reason) > 1000) {
            $this->addError('cancelReason', __('validation.max.string', [
                'attribute' => 'cancel_reason',
                'max' => 1000,
            ]));

            return;
        }

        $hostingModes->cancel($activity, auth()->user(), $reason);
        $this->cancelReason = null;
        session()->flash('status', __('ui.activities.cancelled_status'));
    }

    public function reopen(ActivityHostingModeService $hostingModes): void
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($activity), 403);

        $hostingModes->reopen($activity);
        session()->flash('status', __('ui.activities.reopened_status'));
    }

    public function render()
    {
        $activity = Activity::query()->whereKey($this->activityId)->firstOrFail();

        $activity->load([
            'creator',
            'canceller',
            'activityType',
            'tags.translations',
            'participants.user',
            'waitlist.user',
            'slot.event.enrollmentWindows',
            'slot.place.parent',
            'place.parent',
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

        $stateBlockedMessage = null;
        if ($activity->isCancelled()) {
            $stateBlockedMessage = __('ui.activities.signup_blocked_cancelled');
        } elseif (! $activity->isJoinableMode()) {
            $stateBlockedMessage = __('ui.activities.signup_blocked_not_joinable_mode');
        }

        $canJoin = auth()->check() && ! $isParticipant && ! $onWaitlist && $signupGateOk && $stateBlockedMessage === null;
        $isFull = $activity->max_participants !== null && $activity->participants()->count() >= $activity->max_participants;
        $hasInterest = auth()->check() && auth()->user()->interestedActivities()->where('activities.id', $activity->id)->exists();
        $canManageActivity = auth()->user()?->canModifyEntity($activity) ?? false;

        return view('livewire.activities.show-activity', [
            'activity' => $activity,
            'isParticipant' => $isParticipant,
            'onWaitlist' => $onWaitlist,
            'canJoin' => $canJoin,
            'isFull' => $isFull,
            'hasInterest' => $hasInterest,
            'canManageActivity' => $canManageActivity,
            'signupBlockedMessage' => $signupBlockedMessage,
            'stateBlockedMessage' => $stateBlockedMessage,
        ]);
    }
}
