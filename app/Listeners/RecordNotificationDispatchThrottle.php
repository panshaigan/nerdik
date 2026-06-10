<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Notifications\NotificationDispatchThrottle;
use Illuminate\Notifications\Events\NotificationSent;

class RecordNotificationDispatchThrottle
{
    public function __construct(
        private readonly NotificationDispatchThrottle $throttle
    ) {}

    public function handle(NotificationSent $event): void
    {
        if (! $event->notifiable instanceof User) {
            return;
        }

        $this->throttle->record($event->notification, $event->notifiable);
    }
}
