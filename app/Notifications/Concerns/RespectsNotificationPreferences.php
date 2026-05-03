<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\NotificationPreferenceKey;
use App\Models\User;

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

        return $this->notificationPreferenceKey()->channelsFor($notifiable);
    }
}
