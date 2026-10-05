<?php

declare(strict_types=1);

namespace App\Support\Welcome;

use App\Models\Tag;
use App\Support\Media\CachedPictureSources;
use App\Support\Media\MediaPictureSources;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class WelcomeHeroTagImageResolver
{
    private const CACHE_KEY_PREFIX = 'welcome.hero_tag_image';

    private const CACHE_TTL_SECONDS = 3600;

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['en', 'pl'];

    public function resolve(): ?WelcomeHeroTagImage
    {
        $locale = app()->getLocale();
        $cacheKey = self::cacheKeyForLocale($locale);

        /** @var array{empty?: bool, sources?: array<string, mixed>, label?: string} $payload */
        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->buildCachePayload($locale),
        );

        if (($payload['empty'] ?? false) === true) {
            return null;
        }

        if (! isset($payload['sources'], $payload['label']) || ! is_array($payload['sources'])) {
            return null;
        }

        return new WelcomeHeroTagImage(
            sources: CachedPictureSources::fromArray($payload['sources']),
            label: (string) $payload['label'],
        );
    }

    public static function forgetCachedHeroImages(): void
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            Cache::forget(self::cacheKeyForLocale($locale));
        }
    }

    public static function cacheKeyForLocale(string $locale): string
    {
        return self::CACHE_KEY_PREFIX.'.'.$locale;
    }

    /**
     * @return array{empty: true}|array{sources: array<string, mixed>, label: string}
     */
    private function buildCachePayload(string $locale): array
    {
        $media = $this->randomTagHeroMedia();

        if ($media === null) {
            return ['empty' => true];
        }

        $tag = Tag::query()
            ->with('translations')
            ->find($media->model_id);

        if ($tag === null) {
            return ['empty' => true];
        }

        $label = $this->tagLabel($tag, $locale);
        $sources = MediaPictureSources::fromMediaWithPreset($media, 'tag_hero', $label);

        return [
            'sources' => CachedPictureSources::fromMediaPictureSources($sources)->toArray(),
            'label' => $label,
        ];
    }

    private function randomTagHeroMedia(): ?Media
    {
        $tagMorph = (new Tag)->getMorphClass();

        /** @var Media|null $media */
        $media = Media::query()
            ->join('tags', function ($join) use ($tagMorph): void {
                $join->on('tags.id', '=', 'media.model_id')
                    ->where('media.model_type', '=', $tagMorph);
            })
            ->whereNull('tags.deleted_at')
            ->where('media.collection_name', 'images')
            ->select('media.*')
            ->inRandomOrder()
            ->first();

        return $media;
    }

    private function tagLabel(Tag $tag, string $locale): string
    {
        $localeTranslation = $tag->translations->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: $tag->translations->firstWhere('locale', 'en');

        return (string) ($fallbackTranslation?->label ?? '');
    }
}
