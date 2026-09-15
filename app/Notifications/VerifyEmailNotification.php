<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
}
