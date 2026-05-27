<?php

declare(strict_types=1);

namespace App\Support\Seo;

final readonly class SeoMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $type = 'website',
        public ?string $image = null,
    ) {}
}
