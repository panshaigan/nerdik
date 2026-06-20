<?php

declare(strict_types=1);

namespace App\Support\Welcome;

use App\Support\Media\MediaPictureSources;

final readonly class WelcomeHeroTagImage
{
    public function __construct(
        public MediaPictureSources $sources,
        public string $label,
    ) {}
}
