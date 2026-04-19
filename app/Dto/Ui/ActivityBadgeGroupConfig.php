<?php

namespace App\Dto\Ui;

use App\Enums\ActivityBadgeKind;
use App\Enums\ActivityBadgePreset;
use App\Enums\BadgeSemantic;
use App\Models\Tag;
use App\Support\ActivityBadgeDefaults;
use Illuminate\Support\Collection;

final readonly class ActivityBadgeGroupConfig
{
    /**
     * @param  list<string>|null  $onlyTagCategoryKeys  If set, only these TagCategory keys (overrides `surfaces.*.tag_category_keys` in config)
     * @param  Collection<int, Tag>|null  $tagsOverride  Pre-filtered / merged tags; null = use activity relation
     * @param  array<int, int>  $semanticByKindValue  Map ActivityBadgeKind->value => BadgeSemantic->value overrides
     * @param  array<string, int>  $semanticByTagCategoryKeyValue  Map TagCategory `key` => BadgeSemantic->value (overrides config file)
     */
    public function __construct(
        public ActivityBadgePreset $preset,
        public ?array $onlyTagCategoryKeys = null,
        public ?Collection $tagsOverride = null,
        public array $semanticByKindValue = [],
        public array $semanticByTagCategoryKeyValue = [],
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
}
