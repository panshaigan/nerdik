<?php

declare(strict_types=1);

namespace App\Support\Sharing;

final readonly class SharePayload
{
    public function __construct(
        public string $url,
        public string $title,
        public string $text,
        public string $campaign,
    ) {}
}
