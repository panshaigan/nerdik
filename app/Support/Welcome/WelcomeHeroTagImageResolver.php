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
        /** @var array{media_id: int, label: string}|null $cached */
        $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): ?array {
            $media = Media::query()
                ->where('model_type', Tag::class)
                ->where('collection_name', 'images')
                ->inRandomOrder()
                ->first();

            if ($media === null) {
                return null;
            }

            $tag = Tag::query()
                ->with('translations')
                ->find($media->model_id);

            if ($tag === null) {
                return null;
            }

            return [
                'media_id' => (int) $media->id,
                'label' => $this->tagLabel($tag, app()->getLocale()),
            ];
        });

        if ($cached === null) {
            return null;
        }

        $media = Media::query()->find($cached['media_id']);

        if ($media === null) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        return new WelcomeHeroTagImage(
            sources: MediaPictureSources::fromMediaWithPreset($media, 'tag_card', $cached['label']),
            label: $cached['label'],
        );
    }

    private function tagLabel(Tag $tag, string $locale): string
    {
        $localeTranslation = $tag->translations->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: $tag->translations->firstWhere('locale', 'en');

        return (string) ($fallbackTranslation?->label ?? '');
    }
}
