<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

use function is_numeric;
use function is_string;

class TagSelectionService
{
    /**
     * @param  array<int|string, mixed>  $tagIds
     * @param  array<int|string, mixed>  $newTags
     * @return list<int>
     *
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
        $tag->contexts()->where('context_type', TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)->delete();

        foreach ($types as $type) {
            $id = is_numeric($type) ? (int) $type : null;
            if (is_string($type) && ! is_numeric($type)) {
                $id = ActivityType::query()->where('slug', $type)->value('id');
            }
            if ($id !== null && ActivityType::query()->whereKey($id)->exists()) {
                $tag->contexts()->create([
                    'context_type' => TagContext::CONTEXT_TYPE_ACTIVITY_TYPE,
                    'context_id' => $id,
                ]);
            }
        }
    }

    /**
     * Whether a tag may appear in typeahead suggestions for the given activity type.
     * Tags with no activity_type contexts are universal; otherwise the type must match.
     */
    public function isTagEligibleForActivityTypeSuggestions(Tag $tag, ?int $activityTypeId): bool
    {
        $tag->loadMissing('contexts');

        $ids = $tag->contexts
            ->where('context_type', TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)
            ->pluck('context_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return true;
        }

        if ($activityTypeId === null || $activityTypeId <= 0) {
            return false;
        }

        return $ids->contains($activityTypeId);
    }

    /**
     * JSON-serializable rows for the activity tag picker (all tags; client filters suggestions by context).
     *
     * @param  Collection<int, Tag>  $tags
     * @return list<array<string, mixed>>
     */
    public function tagsPayloadForActivityPicker(Collection $tags, string $locale): array
    {
        $categoryNamesById = TagCategory::query()
            ->with('translations')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (TagCategory $cat) => [(int) $cat->id => (string) $cat->name($locale)])
            ->all();

        return $tags->map(function (Tag $tag) use ($locale, $categoryNamesById) {
            $tag->loadMissing(['translations', 'aliases', 'tagRelations', 'tagCategory.translations', 'contexts']);

            $localeTranslation = $tag->translations->firstWhere('locale', $locale);
            $fallbackTranslation = $localeTranslation ?: $tag->translations->firstWhere('locale', 'en');
            $categoryId = (int) ($tag->tag_category_id ?? 0);
            $categoryName = (string) (($tag->tagCategory?->name($locale) ?? '') ?: ($categoryNamesById[$categoryId] ?? ''));

            $contextActivityTypeIds = $tag->contexts
                ->where('context_type', TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)
                ->pluck('context_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            return [
                'id' => (int) $tag->id,
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'slug' => (string) ($fallbackTranslation?->slug ?? ''),
                'labels' => $tag->translations->mapWithKeys(fn ($t) => [(string) $t->locale => (string) $t->label])->all(),
                'aliases' => $tag->aliases->pluck('alias')->filter()->map(fn ($a) => (string) $a)->values()->all(),
                'related_ids' => $tag->tagRelations->pluck('related_tag_id')->map(fn ($id) => (int) $id)->values()->all(),
                'context_activity_type_ids' => $contextActivityTypeIds,
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, ActivityType>
     */
    public function getActivityTypeContextsAttribute($tag): Collection
    {
        return $tag->contexts()
            ->where('context_type', TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)
            ->get()
            ->map(fn (TagContext $c) => $c->activityType())
            ->filter();
    }
}
