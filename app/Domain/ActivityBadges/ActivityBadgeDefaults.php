<?php

namespace App\Domain\ActivityBadges;

use App\Enums\BadgeSemantic;

final class ActivityBadgeDefaults
{
    public static function semanticForKind(ActivityBadgeKind $kind): BadgeSemantic
    {
        $kindKey = $kind->semanticConfigKey();
        /** @var array<string, int|string|null> $map */
        $map = config('activity-badges.semantic_by_kind', []);
        $value = $map[$kindKey] ?? null;

        return $value !== null && $value !== ''
            ? BadgeSemantic::fromConfig($value)
            : BadgeSemantic::Primary;
    }

    public static function iconForKind(ActivityBadgeKind $kind): ?string
    {
        $kindKey = $kind->semanticConfigKey();
        $value = config('activity-badges.icon_by_kind.'.$kindKey);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
