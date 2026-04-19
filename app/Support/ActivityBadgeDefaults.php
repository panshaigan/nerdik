<?php

namespace App\Support;

use App\Enums\ActivityBadgeKind;
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
}
