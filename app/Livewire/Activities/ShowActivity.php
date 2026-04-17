<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityParticipationViewService;
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

    public function render(ActivityParticipationViewService $participationView)
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

        $vm = $participationView->forShow($activity, auth()->user());

        return view('livewire.activities.show-activity', [
            'activity' => $activity,
            'isParticipant' => $vm->isParticipant,
            'onWaitlist' => $vm->onWaitlist,
            'canJoin' => $vm->canJoin,
            'isFull' => $vm->isFull,
            'hasInterest' => $vm->hasInterest,
            'canManageActivity' => $vm->canManageActivity,
            'signupBlockedMessage' => $vm->signupBlockedMessage,
            'stateBlockedMessage' => $vm->stateBlockedMessage,
            'activeWindowPerActivityMax' => $vm->activeWindowPerActivityMax,
            'activeWindowRemainingForActivity' => $vm->activeWindowRemainingForActivity,
            'activeWindowUserRemaining' => $vm->activeWindowUserRemaining,
        ]);
    }
}
