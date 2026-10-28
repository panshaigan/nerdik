<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\SuggestedBringItem;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Support\Collection;

class ActivitySuggestedBringService
{
    /**
     * @return list<array{id: int, label: string, inherited: bool, host_provides: bool}>
     */
    public function listForActivity(Activity $activity): array
    {
        $activity->loadMissing([
            'activityType.suggestedBringItems',
            'tags.tagCategory',
            'tags.suggestedBringItems',
            'suggestedBringExtras',
            'providedSuggestedBringItems',
        ]);

        $providedIds = $activity->providedSuggestedBringItems
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->mergeRows(
            typeItems: $activity->activityType?->suggestedBringItems ?? collect(),
            gameTagItems: $this->gameTagItemsForTags($activity->tags),
            extraItems: $activity->suggestedBringExtras,
            providedItemIds: $providedIds,
        );
    }

    /**
     * Inherited suggestions for the activity form (direct game tags only, no relation expansion).
     *
     * @param  list<int>  $directTagIds
     * @return list<array{id: int, label: string, inherited: bool, host_provides: bool}>
     */
    public function listInheritedForForm(?int $activityTypeId, array $directTagIds, array $providedItemIds = []): array
    {
        $typeItems = collect();
        if ($activityTypeId !== null && $activityTypeId > 0) {
            $type = ActivityType::query()
                ->with('suggestedBringItems')
                ->find($activityTypeId);
            $typeItems = $type?->suggestedBringItems ?? collect();
        }

        $gameTags = Tag::query()
            ->with(['tagCategory', 'suggestedBringItems'])
            ->whereIn('id', $directTagIds)
            ->get();

        return $this->mergeRows(
            typeItems: $typeItems,
            gameTagItems: $this->gameTagItemsForTags($gameTags),
            extraItems: collect(),
            providedItemIds: $providedItemIds,
        );
    }

    /**
     * @param  list<int>  $providedItemIds
     * @param  list<array{label: string, host_provides?: bool}>  $extras
     */
    public function syncActivityKit(Activity $activity, array $providedItemIds, array $extras): void
    {
        $activity->loadMissing([
            'activityType.suggestedBringItems',
            'tags.tagCategory',
            'tags.suggestedBringItems',
        ]);

        $inheritedIds = $this->inheritedItemIds($activity->activityType?->suggestedBringItems ?? collect(), $this->gameTagItemsForTags($activity->tags));

        $allowedProvided = array_values(array_intersect(
            array_map('intval', $providedItemIds),
            $inheritedIds,
        ));

        $activity->providedSuggestedBringItems()->sync($allowedProvided);

        $activity->suggestedBringExtras()->delete();

        $sort = 0;
        foreach ($extras as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $activity->suggestedBringExtras()->create([
                'label' => mb_substr($label, 0, 255),
                'sort_order' => $sort,
                'host_provides' => (bool) ($row['host_provides'] ?? false),
            ]);
            $sort++;
        }
    }

    /**
     * @param  list<string>  $labels
     */
    public function syncBringLabelsForBringable(ActivityType|Tag $bringable, array $labels): void
    {
        $bringable->suggestedBringItems()->delete();

        $sort = 0;
        foreach ($labels as $raw) {
            $label = trim(is_string($raw) ? $raw : '');
            if ($label === '') {
                continue;
            }

            $bringable->suggestedBringItems()->create([
                'label' => mb_substr($label, 0, 255),
                'sort_order' => $sort,
                'host_provides' => false,
            ]);
            $sort++;
        }
    }

    /**
     * @param  list<string>  $labels
     */
    public function attachKitToNewTag(Tag $tag, array $labels): void
    {
        $this->syncBringLabelsForBringable($tag, $labels);
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @return Collection<int, SuggestedBringItem>
     */
    private function gameTagItemsForTags(Collection $tags): Collection
    {
        return $tags
            ->filter(fn (Tag $tag): bool => $tag->tagCategory?->key === TagCategory::KEY_GAME)
            ->flatMap(fn (Tag $tag) => $tag->suggestedBringItems)
            ->values();
    }

    /**
     * @param  Collection<int, SuggestedBringItem>  $typeItems
     * @param  Collection<int, SuggestedBringItem>  $gameTagItems
     * @param  Collection<int, SuggestedBringItem>  $extraItems
     * @param  list<int>  $providedItemIds
     * @return list<array{id: int, label: string, inherited: bool, host_provides: bool}>
     */
    private function mergeRows(
        Collection $typeItems,
        Collection $gameTagItems,
        Collection $extraItems,
        array $providedItemIds,
    ): array {
        $providedSet = array_fill_keys(array_map('intval', $providedItemIds), true);
        $rows = [];

        foreach ([$typeItems, $gameTagItems] as $group) {
            foreach ($group as $item) {
                $label = trim((string) $item->label);
                if ($label === '') {
                    continue;
                }
                $id = (int) $item->id;
                $rows[] = [
                    'id' => $id,
                    'label' => $label,
                    'inherited' => true,
                    'host_provides' => isset($providedSet[$id]),
                ];
            }
        }

        foreach ($extraItems as $item) {
            $label = trim((string) $item->label);
            if ($label === '') {
                continue;
            }
            $rows[] = [
                'id' => (int) $item->id,
                'label' => $label,
                'inherited' => false,
                'host_provides' => (bool) $item->host_provides,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SuggestedBringItem>  $typeItems
     * @param  Collection<int, SuggestedBringItem>  $gameTagItems
     * @return list<int>
     */
    private function inheritedItemIds(Collection $typeItems, Collection $gameTagItems): array
    {
        return collect()
            ->merge($typeItems)
            ->merge($gameTagItems)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
