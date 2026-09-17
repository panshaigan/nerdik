<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Admin\Resources\Feedback\FeedbackResource;
use App\Models\Feedback;
use App\Notifications\Concerns\BroadcastsWithDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class FeedbackReceivedNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use BroadcastsWithDatabasePayload;
    use Queueable;

    public function __construct(
        public Feedback $feedback
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = FeedbackResource::getUrl('view', ['record' => $this->feedback], panel: 'admin');

        return [
            'type' => 'feedback_received',
            'feedback_id' => $this->feedback->id,
            'feedback_type' => $this->feedback->type->value,
            'subject' => $this->feedback->subject,
            'url' => $url,
            'toast_title' => __('feedback.notifications.received_title'),
            'toast_description' => __('feedback.notifications.received_subtitle', [
                'type' => $this->feedback->type->label(),
                'subject' => $this->feedback->subject,
            ]),
        ];
    }
}
