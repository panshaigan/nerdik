<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Support\Media\MediaPictureSources;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class ListingCardPicture
{
    public const GLOBAL_FALLBACK_ASSET = 'images/tag-game/warhammer.jpg';

    public function __construct(
        public ?MediaPictureSources $sources = null,
        public ?string $staticUrl = null,
    ) {}

    public static function fromMedia(Media $media, string $alt = ''): self
    {
        return new self(
            sources: MediaPictureSources::fromMediaWithPreset($media, 'listing_card', $alt),
        );
    }

    public static function fromStaticAsset(string $publicPath): self
    {
        return new self(staticUrl: asset($publicPath));
    }

    public static function globalFallback(): self
    {
        return self::fromStaticAsset(self::GLOBAL_FALLBACK_ASSET);
    }

    public function hasDisplayableImage(): bool
    {
        return $this->sources !== null || $this->staticUrl !== null;
    }
}
