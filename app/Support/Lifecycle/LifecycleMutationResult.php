<?php

declare(strict_types=1);

namespace App\Support\Lifecycle;

final readonly class LifecycleMutationResult
{
    public function __construct(
        public bool $performed,
        public bool $rateLimited = false,
    ) {}

    public static function performed(): self
    {
        return new self(performed: true);
    }

    public static function skipped(): self
    {
        return new self(performed: false);
    }

    public static function rateLimited(): self
    {
        return new self(performed: false, rateLimited: true);
    }
}
