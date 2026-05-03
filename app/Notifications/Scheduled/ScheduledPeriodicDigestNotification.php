<?php

namespace App\Notifications\Scheduled;

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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('ui.notifications.scheduled.digest_subject', ['date' => $this->localDateLabel]))
            ->line(__('ui.notifications.scheduled.digest_intro'));

        foreach ($this->items as $item) {
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
        return [
            'type' => 'scheduled_periodic_digest',
            'local_date' => $this->localDateLabel,
            'items' => $this->items,
            'toast_title' => __('ui.notifications.scheduled.digest_toast_title'),
            'toast_description' => __('ui.notifications.scheduled.digest_toast_description', ['count' => count($this->items)]),
            'url' => route('notifications.index', [], false),
        ];
    }
}
