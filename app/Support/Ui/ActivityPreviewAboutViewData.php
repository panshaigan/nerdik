<?php

declare(strict_types=1);

namespace App\Support\Ui;

final readonly class ActivityPreviewAboutViewData
{
    public function __construct(
        public ?string $slotName,
        public string $timeLabel,
        public string $locationLabel,
    ) {}
}
