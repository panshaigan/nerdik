<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use App\Support\RichText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class AdminCustomInAppNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public string $subject,
        public string $htmlBody,
        public string $clickUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User) {
            return ['database', 'broadcast'];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_custom_message',
            'subject' => $this->subject,
            'body_html' => RichText::html($this->htmlBody)->toHtml(),
            'url' => $this->clickUrl,
            'toast_title' => $this->subject,
            'toast_description' => RichText::excerpt($this->htmlBody, 500),
        ];
    }
}
