<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Notifications\ActivityRemovedByHostNotification;
use App\Notifications\ActivityReopenedNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class NotificationDispatchThrottle
{
    public function shouldSuppress(Notification $notification, User $notifiable): bool
    {
        if (! config('notification_throttle.enabled', true)) {
            return false;
        }

        $key = $this->keyFor($notification, $notifiable);

        if ($key === null) {
            return false;
        }

        return $this->isThrottled($key);
    }

    public function isThrottled(string $key): bool
    {
        return Cache::has($this->cacheKey($key));
    }

    public function record(Notification $notification, User $notifiable): void
    {
        if (! config('notification_throttle.enabled', true)) {
            return;
        }

        $key = $this->keyFor($notification, $notifiable);

        if ($key === null) {
            return;
        }

        $seconds = $this->ttlFor($notification);

        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->cacheKey($key), true, $seconds);
    }

    public function keyFor(Notification $notification, User $notifiable): ?string
    {
        $notifiableId = (int) $notifiable->id;

        if ($notifiableId <= 0) {
            return null;
        }

        return match ($notification::class) {
            ActivityParticipantJoinedNotification::class => $this->activityKey('joined', $notifiableId, (int) $notification->activity->id),
            ActivityParticipantLeftNotification::class => $this->activityKey('left', $notifiableId, (int) $notification->activity->id),
            WaitlistPromotedNotification::class => $this->activityKey('promoted', $notifiableId, (int) $notification->activity->id),
            ActivityCancelledNotification::class => $this->activityKey('act_cancel', $notifiableId, (int) $notification->activity->id),
            ActivityReopenedNotification::class => $this->activityKey('act_reopen', $notifiableId, (int) $notification->activity->id),
            EventCancelledNotification::class => "evt_cancel:{$notifiableId}:{$notification->eventId}",
            EventReopenedNotification::class => $this->eventKey('evt_reopen', $notifiableId, $notification->event),
            ActivityRemovedByHostNotification::class => $this->activityKey('removed', $notifiableId, (int) $notification->activity->id),
            ProposalSubmittedNotification::class => "proposal:{$notifiableId}:{$notification->proposal->id}",
            default => null,
        };
    }

    private function activityKey(string $prefix, int $notifiableId, int $activityId): ?string
    {
        if ($activityId <= 0) {
            return null;
        }

        return "{$prefix}:{$notifiableId}:{$activityId}";
    }

    private function eventKey(string $prefix, int $notifiableId, mixed $event): ?string
    {
        $eventId = is_object($event) ? (int) $event->getKey() : (int) $event;

        if ($eventId <= 0) {
            return null;
        }

        return "{$prefix}:{$notifiableId}:{$eventId}";
    }

    private function ttlFor(Notification $notification): int
    {
        /** @var array<class-string<Notification>, int> $map */
        $map = config('notification_throttle.cooldown_seconds', []);

        return (int) ($map[$notification::class] ?? 0);
    }

    private function cacheKey(string $key): string
    {
        return 'notification_dispatch_throttle:'.$key;
    }
}
