<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class TagSelectionService
{
    public const CATEGORY_OPTIONS = [
        'game',
        'publisher',
        'world',
        'convention',
        'engine',
        'trigger',
        'block',
        'misc',
    ];

    /**
     * @param  array<int|string, mixed>  $tagIds
     * @param  array<int|string, mixed>  $newTags
     * @return list<int>
     */
    public function resolveFinalTagIds(array $tagIds, array $newTags = []): array
    {
        $baseIds = collect($tagIds)->map(fn ($id) => (int) $id)->filter()->values()->all();

        $createdOrMatchedIds = [];
        foreach ($newTags as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($label === '' || $category === '') {
                continue;
            }

            $createdOrMatchedIds[] = $this->findOrCreateTagByLabel($label, $category);
        }

        $all = array_values(array_unique(array_merge($baseIds, $createdOrMatchedIds)));

        return $this->expandTagIdsViaRelations($all);
    }

    public function findOrCreateTagByLabel(string $label, string $category): int
    {
        $lower = mb_strtolower(trim($label));
        $locale = app()->getLocale();

        $existing = Tag::query()
            ->whereHas('translations', function ($q) use ($lower) {
                $q->whereRaw('LOWER(label) = ?', [$lower]);
            })
            ->orWhereHas('aliases', function ($q) use ($lower) {
                $q->whereRaw('LOWER(alias) = ?', [$lower]);
            })
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::transaction(function () use ($label, $category, $locale) {
            $tag = Tag::create([
                'category' => mb_strtolower(trim($category)),
            ]);

            $tag->translations()->create([
                'locale' => $locale,
                'label' => $label,
            ]);
            if ($locale !== 'en') {
                $tag->translations()->firstOrCreate(
                    ['locale' => 'en'],
                    ['label' => $label]
                );
            }

            return $tag->id;
        });
    }

    /**
     * Include tag IDs reachable via `tag_relations` (tag_id → related_tag_id).
     *
     * @param  list<int>  $tagIds
     * @return list<int>
     */
    public function expandTagIdsViaRelations(array $tagIds): array
    {
        $all = array_values(array_unique($tagIds));
        $queue = $all;

        while (! empty($queue)) {
            $chunk = $queue;
            $queue = [];

            $linkedIds = DB::table('tag_relations')
                ->whereIn('tag_id', $chunk)
                ->pluck('related_tag_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($linkedIds as $id) {
                if (! in_array($id, $all, true)) {
                    $all[] = $id;
                    $queue[] = $id;
                }
            }
        }

        sort($all);

        return $all;
    }
}
