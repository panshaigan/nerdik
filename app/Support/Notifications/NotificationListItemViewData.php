<?php

declare(strict_types=1);

namespace App\Support\Notifications;

final readonly class NotificationListItemViewData
{
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public string $timeAgo,
        public ?string $icon,
        public bool $isUnread,
    ) {}
}
