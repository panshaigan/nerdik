<?php

namespace App\Domain\ActivityBadges;

use App\Enums\BadgeSemantic;
use App\Models\Tag;
use Illuminate\Support\Collection;

final readonly class ActivityBadgeGroupConfig
{
    /**
     * @param  list<string>|null  $onlyTagCategoryKeys  If set, only these TagCategory keys (overrides `surfaces.*.tag_category_keys` in config)
     * @param  Collection<int, Tag>|null  $tagsOverride  Pre-filtered / merged tags; null = use activity relation
     * @param  array<int, int>  $semanticByKindValue  Map ActivityBadgeKind->value => BadgeSemantic->value overrides
     * @param  array<string, int>  $semanticByTagCategoryKeyValue  Map TagCategory `key` => BadgeSemantic->value (overrides config file)
     * @param  array<int, string>  $iconByKind  Map ActivityBadgeKind->value => icon name (overrides config file)
     * @param  array<string, string>  $iconByTagCategoryKey  Map TagCategory `key` => icon name (overrides config file)
     */
    public function __construct(
        public ActivityBadgePreset $preset,
        public ?array $onlyTagCategoryKeys = null,
        public ?Collection $tagsOverride = null,
        public array $semanticByKindValue = [],
        public array $semanticByTagCategoryKeyValue = [],
        public array $iconByKind = [],
        public array $iconByTagCategoryKey = [],
    ) {}

    public static function activityHero(array $semanticByKindValue = [], array $semanticByTagCategoryKeyValue = []): self
    {
        return new self(ActivityBadgePreset::ActivityHero, null, null, $semanticByKindValue, $semanticByTagCategoryKeyValue);
    }

    public static function eventSlotCard(array $semanticByKindValue = [], array $semanticByTagCategoryKeyValue = []): self
    {
        return new self(ActivityBadgePreset::EventSlotCard, null, null, $semanticByKindValue, $semanticByTagCategoryKeyValue);
    }

    public static function browseCard(array $semanticByKindValue = [], array $semanticByTagCategoryKeyValue = []): self
    {
        return new self(ActivityBadgePreset::BrowseCard, null, null, $semanticByKindValue, $semanticByTagCategoryKeyValue);
    }

    public static function eventProposal(array $semanticByKindValue = [], array $semanticByTagCategoryKeyValue = []): self
    {
        return new self(ActivityBadgePreset::EventProposal, null, null, $semanticByKindValue, $semanticByTagCategoryKeyValue);
    }

    public function semanticFor(ActivityBadgeKind $kind): BadgeSemantic
    {
        $v = $this->semanticByKindValue[$kind->value] ?? null;

        return $v !== null ? BadgeSemantic::from($v) : ActivityBadgeDefaults::semanticForKind($kind);
    }

    /**
     * Tone for a taxonomy tag: DTO override per category, then `config('activity-badges.semantic_by_tag_category')`,
     * then global {@see ActivityBadgeKind::TaxonomyTag} from `config('activity-badges.semantic_by_kind')` (unless
     * {@see $semanticByKindValue} forces all tags).
     */
    public function semanticForTaxonomyTag(?string $tagCategoryKey): BadgeSemantic
    {
        $taxonomyOverride = $this->semanticByKindValue[ActivityBadgeKind::TaxonomyTag->value] ?? null;
        if ($taxonomyOverride !== null) {
            return BadgeSemantic::from($taxonomyOverride);
        }
        if ($tagCategoryKey !== null && array_key_exists($tagCategoryKey, $this->semanticByTagCategoryKeyValue)) {
            return BadgeSemantic::from($this->semanticByTagCategoryKeyValue[$tagCategoryKey]);
        }
        $fromFile = $tagCategoryKey !== null ? config('activity-badges.semantic_by_tag_category.'.$tagCategoryKey) : null;
        if ($fromFile !== null && $fromFile !== '') {
            return BadgeSemantic::fromConfig($fromFile);
        }

        return ActivityBadgeDefaults::semanticForKind(ActivityBadgeKind::TaxonomyTag);
    }

    public function iconFor(ActivityBadgeKind $kind): ?string
    {
        $v = $this->iconByKind[$kind->value] ?? null;
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return ActivityBadgeDefaults::iconForKind($kind);
    }

    public function iconForTaxonomyTag(?string $tagCategoryKey): ?string
    {
        $taxonomyOverride = $this->iconByKind[ActivityBadgeKind::TaxonomyTag->value] ?? null;
        if (is_string($taxonomyOverride) && $taxonomyOverride !== '') {
            return $taxonomyOverride;
        }

        if ($tagCategoryKey !== null && array_key_exists($tagCategoryKey, $this->iconByTagCategoryKey)) {
            $icon = $this->iconByTagCategoryKey[$tagCategoryKey];

            return is_string($icon) && $icon !== '' ? $icon : null;
        }

        $fromFile = $tagCategoryKey !== null ? config('activity-badges.icon_by_tag_category.'.$tagCategoryKey) : null;
        if (is_string($fromFile) && $fromFile !== '') {
            return $fromFile;
        }

        return ActivityBadgeDefaults::iconForKind(ActivityBadgeKind::TaxonomyTag);
    }
}
