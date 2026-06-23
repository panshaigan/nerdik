<?php

declare(strict_types=1);

namespace App\Support\Welcome;

use App\Models\Tag;
use App\Support\Media\MediaPictureSources;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class WelcomeHeroTagImageResolver
{
    private const CACHE_KEY = 'welcome.hero_tag_image';

    private const CACHE_TTL_SECONDS = 3600;

    public function resolve(): ?WelcomeHeroTagImage
    {
        /** @var int|null $mediaId */
        $mediaId = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): ?int {
            $media = Media::query()
                ->where('model_type', Tag::class)
                ->where('collection_name', 'images')
                ->inRandomOrder()
                ->first();

            return $media !== null ? (int) $media->id : null;
        });

        if ($mediaId === null) {
            return null;
        }

        $media = Media::query()->find($mediaId);

        if ($media === null) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        $tag = Tag::query()
            ->with('translations')
            ->find($media->model_id);

        if ($tag === null) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        $label = $this->tagLabel($tag, app()->getLocale());

        return new WelcomeHeroTagImage(
            sources: MediaPictureSources::fromMediaWithPreset($media, 'tag_hero', $label),
            label: $label,
        );
    }

    private function tagLabel(Tag $tag, string $locale): string
    {
        $localeTranslation = $tag->translations->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: $tag->translations->firstWhere('locale', 'en');

        return (string) ($fallbackTranslation?->label ?? '');
    }
}
