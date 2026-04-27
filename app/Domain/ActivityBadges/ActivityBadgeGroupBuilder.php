<?php

namespace App\Domain\ActivityBadges;

use App\Enums\BadgeSemantic;
use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Support\Collection;

class ActivityBadgeGroupBuilder
{
    /**
     * @return list<ActivityBadgeItem>
     */
    public function build(Activity $activity, ActivityBadgeGroupConfig $config): array
    {
        $surface = $this->surfaceKey($config->preset);
        /** @var array<string, mixed> $surfaceCfg */
        $surfaceCfg = config('activity-badges.surfaces.'.$surface, []);

        $chipOrder = config('activity-badges.chip_order', []);
        $orderIndices = [];
        foreach ($chipOrder as $index => $key) {
            if (is_string($key)) {
                $orderIndices[$key] = $index;
            }
        }

        $rows = [];

        if (($surfaceCfg['activity_type'] ?? false) && $activity->activityType?->slug) {
            $slug = $activity->activityType->slug;
            $rows[] = [
                'order' => $orderIndices['activity_type'] ?? 0,
                'tie' => (int) ($activity->activity_type_id ?? 0),
                'item' => new ActivityBadgeItem(
                    ActivityBadgeKind::ActivityType,
                    'activity_type:'.$activity->activity_type_id,
                    __('ui.activities.types.'.$slug),
                    $config->semanticFor(ActivityBadgeKind::ActivityType),
                    $config->iconFor(ActivityBadgeKind::ActivityType),
                    false,
                    true,
                ),
            ];
        }

        $allowedTagCats = $config->onlyTagCategoryKeys ?? ($surfaceCfg['tag_category_keys'] ?? null);
        $tags = $this->resolveTags($activity, $config, $allowedTagCats);

        foreach ($tags as $tag) {
            $category = $tag->category;
            if ($category === null || $category === '') {
                continue;
            }
            $slotKey = 'tags:'.$category;
            $order = $orderIndices[$slotKey] ?? 800;
            $rows[] = [
                'order' => $order,
                'tie' => $tag->id,
                'item' => new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:'.$tag->id,
                    $this->tagLabel($tag),
                    $config->semanticForTaxonomyTag($category),
                    $config->iconForTaxonomyTag($category),
                    false,
                    true,
                    null,
                    $tag->tagCategory?->name(app()->getLocale()),
                ),
            ];
        }

        if (($surfaceCfg['requires_approval'] ?? false) && $activity->requires_approval) {
            $rows[] = [
                'order' => $orderIndices['meta:requires_approval'] ?? 50,
                'tie' => 0,
                'item' => new ActivityBadgeItem(
                    ActivityBadgeKind::RequiresApproval,
                    'meta:requires_approval',
                    __('ui.activities.requires_approval_badge'),
                    $config->semanticFor(ActivityBadgeKind::RequiresApproval),
                    $config->iconFor(ActivityBadgeKind::RequiresApproval),
                    false,
                    true,
                ),
            ];
        }
        if (($surfaceCfg['allows_observers'] ?? false) && $activity->allows_observers) {
            $rows[] = [
                'order' => $orderIndices['meta:allows_observers'] ?? 51,
                'tie' => 0,
                'item' => new ActivityBadgeItem(
                    ActivityBadgeKind::AllowsObservers,
                    'meta:allows_observers',
                    __('ui.activities.allows_observers_badge'),
                    $config->semanticFor(ActivityBadgeKind::AllowsObservers),
                    $config->iconFor(ActivityBadgeKind::AllowsObservers),
                    false,
                    true,
                ),
            ];
        }
        if (($surfaceCfg['minimum_age'] ?? false) && filled($activity->minimum_age)) {
            $rows[] = [
                'order' => $orderIndices['meta:minimum_age'] ?? 52,
                'tie' => 0,
                'item' => new ActivityBadgeItem(
                    ActivityBadgeKind::MinimumAge,
                    'meta:minimum_age',
                    $activity->minimum_age.'+',
                    $config->semanticFor(ActivityBadgeKind::MinimumAge),
                    $config->iconFor(ActivityBadgeKind::MinimumAge),
                ),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['order'] !== $b['order']) {
                return $a['order'] <=> $b['order'];
            }

            return $a['tie'] <=> $b['tie'];
        });

        return array_map(static fn (array $r): ActivityBadgeItem => $r['item'], $rows);
    }

    /**
     * Activity type chips for contexts without an Activity model (e.g. open slot allowed types).
     *
     * @param  iterable<int, string>  $typeLabels  Already translated display strings
     * @return list<ActivityBadgeItem>
     */
    public function buildActivityTypeChips(
        iterable $typeLabels,
        BadgeSemantic $semantic = BadgeSemantic::Info,
        ?string $icon = null
    ): array {
        $resolvedIcon = $icon ?? ActivityBadgeDefaults::iconForKind(ActivityBadgeKind::ActivityType);
        $items = [];
        $i = 0;
        foreach ($typeLabels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $items[] = new ActivityBadgeItem(
                ActivityBadgeKind::ActivityType,
                'activity_type:slot:'.$i,
                $label,
                $semantic,
                $resolvedIcon,
            );
            $i++;
        }

        return $items;
    }

    public function tagLabel(Tag $tag, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $translation = $tag->translations->firstWhere('locale', $locale)
            ?? $tag->translations->first();

        return $translation?->label
            ?? $translation?->slug
            ?? '#'.$tag->id;
    }

    private function surfaceKey(ActivityBadgePreset $preset): string
    {
        return match ($preset) {
            ActivityBadgePreset::ActivityHero => 'activity_hero',
            ActivityBadgePreset::EventSlotCard => 'event_slot',
            ActivityBadgePreset::BrowseCard => 'browse_card',
            ActivityBadgePreset::EventProposal => 'event_proposal',
        };
    }

    /**
     * @param  list<string>|null  $allowedCategoryKeys
     * @return Collection<int, Tag>
     */
    private function resolveTags(Activity $activity, ActivityBadgeGroupConfig $config, ?array $allowedCategoryKeys): Collection
    {
        /** @var Collection<int, Tag> $tags */
        $tags = $config->tagsOverride ?? $activity->tags;
        $tags = $tags->values();

        if ($allowedCategoryKeys !== null) {
            $tags = $tags->filter(fn (Tag $t) => in_array($t->category, $allowedCategoryKeys, true))->values();
        }

        return $tags;
    }
}
