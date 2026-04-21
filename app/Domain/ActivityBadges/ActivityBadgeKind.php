<?php

namespace App\Domain\ActivityBadges;

enum ActivityBadgeKind: int
{
    case TaxonomyTag = 1;
    case ActivityType = 2;
    case MinimumAge = 3;
    case RequiresApproval = 4;
    case AllowsObservers = 5;

    /** Key under `config('activity-badges.semantic_by_kind')`. */
    public function semanticConfigKey(): string
    {
        return match ($this) {
            self::TaxonomyTag => 'taxonomy_tag',
            self::ActivityType => 'activity_type',
            self::MinimumAge => 'minimum_age',
            self::RequiresApproval => 'requires_approval',
            self::AllowsObservers => 'allows_observers',
        };
    }
}
