<?php

namespace App\Dto\Ui;

use App\Enums\ActivityBadgeKind;
use App\Enums\BadgeSemantic;

final readonly class ActivityBadgeItem
{
    public function __construct(
        public ActivityBadgeKind $kind,
        public string $key,
        public string $label,
        public BadgeSemantic $semantic,
        public bool $outline = true,
        public bool $normalWrap = false,
        public ?string $dataUi = null,
        public ?string $title = null,
    ) {}
}
