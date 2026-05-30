<?php

declare(strict_types=1);

namespace App\Support\Seeders;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class SeederTagImageMatcher
{
    /**
     * @return array{id: ?int, slug: string}
     */
    public function parseBasename(string $basename): array
    {
        $stem = pathinfo($basename, PATHINFO_FILENAME);

        if (preg_match('/^(\d+)_(.+)$/u', $stem, $matches) === 1) {
            return [
                'id' => (int) $matches[1],
                'slug' => $this->nameStemToSlug($matches[2]),
            ];
        }

        return [
            'id' => null,
            'slug' => $this->nameStemToSlug($stem),
        ];
    }

    public function resolve(?int $id, string $slug, ?string $categoryKey = null): ?Tag
    {
        if ($id !== null) {
            $tag = $this->scopedQuery($categoryKey)->find($id);

            if ($tag !== null && $this->tagHasSlug($tag, $slug)) {
                return $tag;
            }
        }

        return $this->scopedQuery($categoryKey)
            ->whereHas('translations', fn (Builder $query) => $query->where('slug', $slug))
            ->first();
    }

    private function tagHasSlug(Tag $tag, string $slug): bool
    {
        return $tag->translations()->where('slug', $slug)->exists();
    }

    private function nameStemToSlug(string $stem): string
    {
        $normalized = str_replace('_', ' ', $stem);

        return Str::slug($normalized);
    }

    /**
     * @return Builder<Tag>
     */
    private function scopedQuery(?string $categoryKey): Builder
    {
        $query = Tag::query();

        if ($categoryKey !== null) {
            $query->whereHas('tagCategory', fn (Builder $categoryQuery) => $categoryQuery->where('key', $categoryKey));
        }

        return $query;
    }
}
