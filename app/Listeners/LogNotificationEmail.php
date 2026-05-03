<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

class LogNotificationEmail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $recipientEmails = $this->recipientEmails($event);
        if ($recipientEmails === []) {
            return;
        }

        $notifiable = $event->notifiable;
        $now = now();
        $recipientUserId = $notifiable instanceof User ? (int) $notifiable->getKey() : null;
        $notifiableType = $notifiable instanceof Model ? $notifiable->getMorphClass() : null;
        $notifiableId = $notifiable instanceof Model ? (string) $notifiable->getKey() : null;
        $providerMessageId = $this->providerMessageId($event->response);

        $rows = collect($recipientEmails)
            ->map(fn (string $email): array => [
                'sent_at' => $now,
                'notification_type' => $event->notification::class,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'recipient_user_id' => $recipientUserId,
                'recipient_email' => $email,
                'mailer' => (string) config('mail.default'),
                'provider_message_id' => $providerMessageId,
                'metadata' => json_encode([
                    'channel' => $event->channel,
                    'response_type' => is_object($event->response) ? $event->response::class : gettype($event->response),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('notification_email_logs')->insert($rows);
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(NotificationSent $event): array
    {
        $route = null;
        if (method_exists($event->notifiable, 'routeNotificationFor')) {
            $route = $event->notifiable->routeNotificationFor('mail', $event->notification);
        }

        $emails = [];
        if (is_string($route) && $route !== '') {
            $emails[] = $route;
        }
        if (is_array($route)) {
            foreach ($route as $value) {
                if (is_string($value) && $value !== '') {
                    $emails[] = $value;
                }
            }
        }

        if ($emails === [] && isset($event->notifiable->email) && is_string($event->notifiable->email) && $event->notifiable->email !== '') {
            $emails[] = $event->notifiable->email;
        }

        return collect($emails)
            ->map(static fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(static fn (string $email): bool => $email !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function providerMessageId(mixed $response): ?string
    {
        if (! is_object($response) || ! method_exists($response, 'getMessageId')) {
            return null;
        }

        $messageId = $response->getMessageId();

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }
}
