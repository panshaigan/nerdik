<?php

declare(strict_types=1);

namespace App\Services\AdminEmail;

final readonly class AdminEmailDispatchResult
{
    public function __construct(
        public int $queued,
        public int $skippedIneligible,
        public int $skippedOverLimit,
    ) {}
}
