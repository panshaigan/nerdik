<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use Throwable;

use function is_numeric;
use function is_string;

class TagSelectionService
{
    /**
     * @param array<int|string, mixed> $tagIds
     * @param array<int|string, mixed> $newTags
     * @return list<int>
     * @throws Throwable
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
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($label === '' || $categoryId <= 0) {
                continue;
            }

            $createdOrMatchedIds[] = $this->findOrCreateTagByLabel($label, $categoryId);
        }

        $all = array_values(array_unique(array_merge($baseIds, $createdOrMatchedIds)));

        return $this->expandTagIdsViaRelations($all);
    }

    /**
     * @throws Throwable
     */
    public function findOrCreateTagByLabel(string $label, int $categoryId): int
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

        return $existing->id ?? DB::transaction(static function () use ($label, $categoryId, $locale) {
            $tag = Tag::create([
                'tag_category_id' => $categoryId,
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

    public function syncActivityTypeContexts(Tag $tag, array $types): void
    {
        $tag->contexts()->where('context_type', 'activity_type')->delete();

        foreach ($types as $type) {
            $id = is_numeric($type) ? (int) $type : null;
            if (is_string($type) && ! is_numeric($type)) {
                $id = ActivityType::query()->where('slug', $type)->value('id');
            }
            if ($id !== null && ActivityType::query()->whereKey($id)->exists()) {
                $tag->contexts()->create([
                    'context_type' => 'activity_type',
                    'context_id' => $id,
                ]);
            }
        }
    }

    /**
     * @return Collection<int, ActivityType>
     */
    public function getActivityTypeContextsAttribute($tag): Collection
    {
        return $tag->contexts()
            ->where('context_type', 'activity_type')
            ->get()
            ->map(fn (TagContext $c) => $c->activityType())
            ->filter();
    }
}
