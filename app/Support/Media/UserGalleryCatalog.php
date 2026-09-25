<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class UserGalleryCatalog
{
    public const COLLECTION = 'gallery';

    public const SOURCE_PATH_PROPERTY = 'source_path';

    public const PROFILE_PAGE_SIZE = 12;

    /**
     * @return list<array{media_id: int, sources: MediaPictureSources}>
     */
    public function forUser(User $user): array
    {
        return $user->getMedia(self::COLLECTION)
            ->sortByDesc('id')
            ->values()
            ->map(fn (Media $media): array => [
                'media_id' => (int) $media->id,
                'sources' => MediaPictureSources::fromMediaWithPreset($media, 'listing_card'),
            ])
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, array{media_id: int, sources: MediaPictureSources}>
     */
    public function paginateForUser(User $user, int $perPage = self::PROFILE_PAGE_SIZE): LengthAwarePaginator
    {
        return Media::query()
            ->where('collection_name', self::COLLECTION)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Media $media): array => [
                'media_id' => (int) $media->id,
                'sources' => MediaPictureSources::fromMediaWithPreset($media, 'listing_card'),
            ]);
    }

    public function isAllowedGalleryMediaId(int $mediaId, User $user, ?int $currentlyAttachedId = null): bool
    {
        if ($this->mediaBelongsToUser($mediaId, $user)) {
            return true;
        }

        return $currentlyAttachedId !== null && $mediaId === $currentlyAttachedId;
    }

    /**
     * @return list<int>
     */
    public function availableMediaIds(User $user): array
    {
        return array_map(
            static fn (array $image): int => $image['media_id'],
            $this->forUser($user),
        );
    }

    public function mediaBelongsToUser(int $mediaId, User $user): bool
    {
        return Media::query()
            ->whereKey($mediaId)
            ->where('collection_name', self::COLLECTION)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->exists();
    }

    public function findForUser(int $mediaId, User $user): ?Media
    {
        if (! $this->mediaBelongsToUser($mediaId, $user)) {
            return null;
        }

        return Media::query()->find($mediaId);
    }

    public static function sourceRelativePath(int $mediaId): string
    {
        return 'gallery-sources/'.$mediaId.'.webp';
    }

    public function sourceUrl(Media $media): ?string
    {
        $path = $media->getCustomProperty(self::SOURCE_PATH_PROPERTY);

        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        $fallback = $media->getUrl();

        return $fallback !== '' ? $fallback : null;
    }

    public function previewUrl(Media $media): ?string
    {
        $sources = MediaPictureSources::fromMediaWithPreset($media, 'listing_card');
        $url = $sources->webpSrc();

        return $url !== '' ? $url : null;
    }

    public function deleteSourceFile(Media $media): void
    {
        $path = $media->getCustomProperty(self::SOURCE_PATH_PROPERTY);

        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
