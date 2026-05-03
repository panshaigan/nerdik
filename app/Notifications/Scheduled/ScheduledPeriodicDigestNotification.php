<?php

declare(strict_types=1);

namespace App\Notifications\Scheduled;

use App\Enums\NotificationPreferenceKey;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledPeriodicDigestNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  list<array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $localDateLabel
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database', 'mail'];
        }

        $channels = [];
        if ($this->filteredItems($notifiable, 'in_app') !== []) {
            $channels[] = 'database';
        }
        if ($this->filteredItems($notifiable, 'email') !== []) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $items = $notifiable instanceof User
            ? $this->filteredItems($notifiable, 'email')
            : $this->items;

        $mail = (new MailMessage)
            ->subject(__('ui.notifications.scheduled.digest_subject', ['date' => $this->localDateLabel]))
            ->line(__('ui.notifications.scheduled.digest_intro'));

        foreach ($items as $item) {
            $mail->line('')->line($item['title']);
            foreach ($item['lines'] as $line) {
                $mail->line('- '.$line);
            }
            $mail->line(__('ui.notifications.scheduled.digest_view_link', ['url' => $item['url']]));
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $items = $notifiable instanceof User
            ? $this->filteredItems($notifiable, 'in_app')
            : $this->items;

        return [
            'type' => 'scheduled_periodic_digest',
            'local_date' => $this->localDateLabel,
            'items' => $items,
            'toast_title' => __('ui.notifications.scheduled.digest_toast_title'),
            'toast_description' => __('ui.notifications.scheduled.digest_toast_description', ['count' => count($items)]),
            'url' => route('notifications.index', [], false),
        ];
    }

    /**
     * @param  'in_app'|'email'  $preferenceChannel
     * @return list<array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}>
     */
    private function filteredItems(User $user, string $preferenceChannel): array
    {
        $out = [];
        foreach ($this->items as $item) {
            $key = NotificationPreferenceKey::tryFromScheduledCategory((string) ($item['category'] ?? ''));
            if ($key === null) {
                $out[] = $item;

                continue;
            }
            if ($user->wantsNotificationChannel($key, $preferenceChannel)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
