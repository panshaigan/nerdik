<?php

declare(strict_types=1);

namespace App\Support\Activities;

use App\Models\Tag;
use App\Support\Media\MediaPictureSources;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ActivityTagImageCatalog
{
    /**
     * @param  list<int>  $tagIds
     * @return list<array{tag_id: int, label: string, images: list<array{media_id: int, sources: MediaPictureSources}>}>
     */
    public function forTagIds(array $tagIds, ?string $locale = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $tagIds))));
        if ($ids === []) {
            return [];
        }

        $locale ??= app()->getLocale();

        return Tag::query()
            ->whereIn('id', $ids)
            ->with(['translations', 'media'])
            ->get()
            ->map(function (Tag $tag) use ($locale): ?array {
                $label = $this->tagLabel($tag, $locale);
                $images = $tag->getMedia('images')
                    ->map(fn (Media $media): array => [
                        'media_id' => (int) $media->id,
                        'sources' => MediaPictureSources::fromMediaWithPreset($media, 'tag_card', $label),
                    ])
                    ->values()
                    ->all();

                if ($images === []) {
                    return null;
                }

                return [
                    'tag_id' => (int) $tag->id,
                    'label' => $label,
                    'images' => $images,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $tagIds
     * @return list<int>
     */
    public function availableMediaIds(array $tagIds): array
    {
        $ids = [];
        foreach ($this->forTagIds($tagIds) as $group) {
            foreach ($group['images'] as $image) {
                $ids[] = $image['media_id'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $tagIds
     */
    public function mediaBelongsToSelectedTags(int $mediaId, array $tagIds): bool
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $tagIds))));
        if ($ids === []) {
            return false;
        }

        return Media::query()
            ->whereKey($mediaId)
            ->where('collection_name', 'images')
            ->where('model_type', Tag::class)
            ->whereIn('model_id', $ids)
            ->exists();
    }

    private function tagLabel(Tag $tag, string $locale): string
    {
        $localeTranslation = $tag->translations->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: $tag->translations->firstWhere('locale', 'en');

        return (string) ($fallbackTranslation?->label ?? '');
    }
}
