<?php

namespace App\Jobs;

use App\Mail\WaitlistPromotedMail;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class NotifyWaitlistPromotedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public Activity $activity
    ) {}

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WaitlistPromotedMail($this->user, $this->activity));
    }
}
