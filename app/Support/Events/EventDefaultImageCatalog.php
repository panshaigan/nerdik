<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Support\Media\MediaPictureSources;
use App\Support\Ui\EventListingImageResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EventDefaultImageCatalog
{
    /**
     * @return list<array{media_id: int, sources: MediaPictureSources}>
     */
    public function all(): array
    {
        return Media::query()
            ->where('collection_name', EventListingImageResolver::EVENT_LISTING_COLLECTION)
            ->orderBy('id')
            ->get()
            ->map(fn (Media $media): array => [
                'media_id' => (int) $media->id,
                'sources' => MediaPictureSources::fromMediaWithPreset($media, 'listing_card'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function availableMediaIds(): array
    {
        return array_map(
            static fn (array $image): int => $image['media_id'],
            $this->all(),
        );
    }

    public function mediaIsAvailable(int $mediaId): bool
    {
        return in_array($mediaId, $this->availableMediaIds(), true);
    }
}
