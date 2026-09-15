<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notifications\Scheduled\ScheduledPeriodicDigestSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendScheduledPeriodicDigestJob implements ShouldQueue
{
    use Queueable;

    public function handle(ScheduledPeriodicDigestSender $sender): void
    {
        $sender->sendDueDigests();
    }
}
