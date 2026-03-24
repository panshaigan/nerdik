<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyWaitlistPromotedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public Activity $activity
    ) {}

    public function handle(): void
    {
        $this->user->notify(new WaitlistPromotedNotification($this->activity));
    }
}
