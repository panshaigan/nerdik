<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Support\Media\MediaPictureSources;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class ListingCardPicture
{
    public function __construct(
        public ?MediaPictureSources $sources = null,
    ) {}

    public static function fromMedia(Media $media, string $alt = '', string $preset = 'listing_card'): self
    {
        return new self(
            sources: MediaPictureSources::fromMediaWithPreset($media, $preset, $alt),
        );
    }

    public static function empty(): self
    {
        return new self;
    }

    public function hasDisplayableImage(): bool
    {
        return $this->sources !== null;
    }
}
