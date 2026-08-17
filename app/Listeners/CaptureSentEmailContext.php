<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\SentEmailKind;
use App\Models\User;
use App\Notifications\VerifyPendingEmailNotification;
use App\Services\SentEmails\SentEmailRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Context;

class CaptureSentEmailContext
{
    public function handle(NotificationSending $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $notification = $event->notification;
        $related = $this->related($notification);
        $recipientUserId = $this->recipientUserId($event);

        Context::add(SentEmailRecorder::CONTEXT_KEY, [
            'kind' => $this->kind($notification)->value,
            'source_class' => $notification::class,
            'recipient_user_id' => $recipientUserId,
            'related_type' => $related instanceof Model ? $related->getMorphClass() : null,
            'related_id' => $related instanceof Model ? $related->getKey() : null,
        ]);
    }

    private function kind(Notification $notification): SentEmailKind
    {
        if ($notification instanceof ProvidesSentEmailContext) {
            return $notification->sentEmailKind();
        }

        if (method_exists($notification, 'sentEmailKind')) {
            $kind = $notification->sentEmailKind();
            if ($kind instanceof SentEmailKind) {
                return $kind;
            }
        }

        return SentEmailKind::fromSourceClass($notification::class);
    }

    private function related(Notification $notification): ?Model
    {
        if ($notification instanceof ProvidesSentEmailContext) {
            return $notification->sentEmailRelated();
        }

        if (method_exists($notification, 'sentEmailRelated')) {
            $related = $notification->sentEmailRelated();
            if ($related instanceof Model) {
                return $related;
            }
        }

        return null;
    }

    private function recipientUserId(NotificationSending $event): ?int
    {
        if ($event->notifiable instanceof User) {
            return (int) $event->notifiable->getKey();
        }

        $notification = $event->notification;
        if ($notification instanceof VerifyPendingEmailNotification) {
            return (int) $notification->user->getKey();
        }

        return null;
    }
}
