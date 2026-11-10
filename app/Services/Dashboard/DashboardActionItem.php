<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

final class DashboardActionItem
{
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $meta = null,
        public readonly string $icon = 'o-inbox',
    ) {}
}
