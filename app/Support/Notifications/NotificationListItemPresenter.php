<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationListItemPresenter
{
    public function from(DatabaseNotification $notification): NotificationListItemViewData
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $type = is_string($data['type'] ?? null) ? $data['type'] : 'unknown';

        [$title, $icon] = $this->resolveTitleAndIcon($type, $data);

        return new NotificationListItemViewData(
            title: $title,
            subtitle: $this->resolveSubtitle($type, $data),
            timeAgo: $notification->created_at->diffForHumans(),
            icon: $icon,
            isUnread: $notification->read_at === null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?string}
     */
    private function resolveTitleAndIcon(string $type, array $data): array
    {
        return match ($type) {
            'proposal_submitted' => [__('ui.notifications.proposal_submitted_list'), 'o-inbox-arrow-down'],
            'proposal_accepted' => [__('Proposal accepted'), 'o-check-circle'],
            'proposal_rejected' => [__('Proposal rejected'), 'o-x-circle'],
            'waitlist_promoted' => [__('You got a place!'), 'o-arrow-trending-up'],
            'activity_cancelled' => [__('ui.notifications.activity_cancelled_list'), 'o-no-symbol'],
            'event_cancelled' => [__('ui.notifications.event_cancelled_list'), 'o-calendar-days'],
            'activity_reopened' => [__('ui.notifications.activity_reopened_list'), 'o-arrow-path'],
            'event_reopened' => [__('ui.notifications.event_reopened_list'), 'o-arrow-path'],
            default => [$this->fallbackTitle($data), 'o-bell'],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSubtitle(string $type, array $data): ?string
    {
        $activity = trim((string) ($data['activity_name'] ?? ''));
        $event = trim((string) ($data['event_name'] ?? ''));

        return match ($type) {
            'event_cancelled', 'event_reopened' => $event !== '' ? $event : null,
            'waitlist_promoted' => $activity !== '' ? $activity : null,
            default => $this->joinLabelParts($activity, $event),
        };
    }

    private function joinLabelParts(string ...$parts): ?string
    {
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fallbackTitle(array $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : __('Notification');
    }
}
