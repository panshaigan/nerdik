<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
}
