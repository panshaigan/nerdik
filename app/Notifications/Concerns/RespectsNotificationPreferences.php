<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\NotificationPreferenceKey;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchThrottle;

trait RespectsNotificationPreferences
{
    abstract protected function notificationPreferenceKey(): NotificationPreferenceKey;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database', 'broadcast', 'mail'];
        }

        if (app(NotificationDispatchThrottle::class)->shouldSuppress($this, $notifiable)) {
            return [];
        }

        return $this->notificationPreferenceKey()->channelsFor($notifiable);
    }
}
