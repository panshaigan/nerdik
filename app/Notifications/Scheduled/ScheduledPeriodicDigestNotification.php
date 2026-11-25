<?php

declare(strict_types=1);

namespace App\Notifications\Scheduled;

use App\Contracts\ProvidesSentEmailContext;
use App\Enums\NotificationPreferenceKey;
use App\Enums\SentEmailKind;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use App\Notifications\Concerns\AttachesCalendarIcs;
use App\Support\Calendar\CalendarLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduledPeriodicDigestNotification extends Notification implements ProvidesSentEmailContext, ShouldQueue, ShouldQueueAfterCommit
{
    use AttachesCalendarIcs;
    use Queueable;

    /**
     * @param  list<array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string, enrollment_window_id?: int}>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $localDateLabel
    ) {}

    public function sentEmailKind(): SentEmailKind
    {
        return SentEmailKind::ScheduledDigest;
    }

    public function sentEmailRelated(): ?Model
    {
        return null;
    }

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
            $absoluteUrl = url($item['url']);
            $label = $this->digestLinkLabel((string) ($item['category'] ?? ''));
            $mail->line("[{$label}]({$absoluteUrl})");
        }

        return $this->attachEnrollmentWindowCalendars($mail, $items);
    }

    /**
     * @param  list<array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string, enrollment_window_id?: int}>  $items
     */
    private function attachEnrollmentWindowCalendars(MailMessage $mail, array $items): MailMessage
    {
        $windowIds = [];
        foreach ($items as $item) {
            if (($item['category'] ?? '') !== 'interested_enrollment_window') {
                continue;
            }
            $id = $item['enrollment_window_id'] ?? null;
            if (is_int($id)) {
                $windowIds[$id] = true;
            }
        }

        if ($windowIds === []) {
            return $mail;
        }

        $windows = EventEnrollmentWindow::query()
            ->with(['event.places.city'])
            ->whereIn('id', array_keys($windowIds))
            ->get()
            ->keyBy('id');

        $calendarLinks = app(CalendarLinks::class);
        $attachedIds = [];

        foreach (array_keys($windowIds) as $windowId) {
            if (isset($attachedIds[$windowId])) {
                continue;
            }

            $window = $windows->get($windowId);
            if ($window === null) {
                continue;
            }

            $payload = $calendarLinks->forEnrollmentWindow($window);
            if ($payload === null) {
                continue;
            }

            $mail = $this->attachCalendarIcs($mail, $payload);
            $attachedIds[$windowId] = true;
        }

        return $mail;
    }

    private function digestLinkLabel(string $category): string
    {
        return match ($category) {
            'interested_enrollment_window', 'participant_follow_next_edition' => __('ui.notifications.view_event'),
            'host_propose_next_edition' => __('ui.notifications.propose_activity'),
            'dashboard_feed' => __('ui.notifications.view_dashboard'),
            'participant_cancellation_deadline', 'organizer_low_participation', 'host_low_participation', 'host_upcoming_mark_absences', 'host_mark_absences' => __('ui.notifications.view_activity'),
            default => __('ui.notifications.view_activity'),
        };
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
