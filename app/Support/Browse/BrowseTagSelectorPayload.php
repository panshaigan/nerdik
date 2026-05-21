<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Support\Collection;

final class BrowseTagSelectorPayload
{
    /**
     * @param  Collection<int, Tag>|iterable<int, Tag>  $tags
     * @param  array<int, string>  $categoryNamesById
     * @param  array<int, string>  $categoryKeysById
     * @return list<array<string, mixed>>
     */
    public static function fromCollection(
        iterable $tags,
        string $locale,
        array $categoryNamesById = [],
        array $categoryKeysById = [],
        bool $includeRelatedIds = true,
    ): array {
        return collect($tags)
            ->map(fn (Tag $tag) => self::fromTag(
                $tag,
                $locale,
                $categoryNamesById,
                $categoryKeysById,
                $includeRelatedIds,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $categoryNamesById
     * @param  array<int, string>  $categoryKeysById
     * @return array<string, mixed>
     */
    public static function fromTag(
        Tag $tag,
        string $locale,
        array $categoryNamesById = [],
        array $categoryKeysById = [],
        bool $includeRelatedIds = true,
    ): array {
        $localeTranslation = collect($tag->translations ?? [])->firstWhere('locale', $locale);
        $fallbackTranslation = $localeTranslation ?: collect($tag->translations ?? [])->firstWhere('locale', 'en');
        $categoryId = (int) ($tag->tag_category_id ?? 0);
        $categoryName = (string) (($tag->tagCategory?->name($locale) ?? '') ?: ($categoryNamesById[$categoryId] ?? ''));
        $categoryKey = (string) (($tag->tagCategory?->key ?? '') ?: ($categoryKeysById[$categoryId] ?? ''));

        $payload = [
            'id' => (int) $tag->id,
            'category_id' => $categoryId,
            'category_key' => $categoryKey,
            'category_name' => $categoryName,
            'slug' => (string) ($fallbackTranslation?->slug ?? ''),
            'labels' => collect($tag->translations ?? [])->mapWithKeys(fn ($t) => [(string) $t->locale => (string) $t->label])->all(),
            'aliases' => collect($tag->aliases ?? [])->pluck('alias')->filter()->map(fn ($a) => (string) $a)->values()->all(),
            'popularity_score' => (int) ($tag->popularity_score ?? 0),
        ];

        if ($includeRelatedIds) {
            $payload['related_ids'] = collect($tag->tagRelations ?? [])->pluck('related_tag_id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return $payload;
    }

    /**
     * @param  list<array{id: int, key: string, name: string}>  $categories
     * @return array{namesById: array<int, string>, keysById: array<int, string>}
     */
    public static function categoryMapsFromConfig(array $categories): array
    {
        return [
            'namesById' => collect($categories)->mapWithKeys(fn (array $c) => [(int) $c['id'] => (string) $c['name']])->all(),
            'keysById' => collect($categories)->mapWithKeys(fn (array $c) => [(int) $c['id'] => (string) $c['key']])->all(),
        ];
    }

    /**
     * @return list<array{id: int, key: string, name: string}>
     */
    public static function categoriesForLocale(string $locale): array
    {
        return TagCategory::query()
            ->with('translations')
            ->orderBy('key')
            ->get()
            ->map(fn (TagCategory $cat) => [
                'id' => (int) $cat->id,
                'key' => (string) $cat->key,
                'name' => (string) $cat->name($locale),
            ])
            ->values()
            ->all();
    }
}
