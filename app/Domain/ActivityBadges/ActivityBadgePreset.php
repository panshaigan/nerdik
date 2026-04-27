<?php

namespace App\Domain\ActivityBadges;

enum ActivityBadgePreset: int
{
    /** Activity hero: taxonomy tags, then approval / observers / age meta. */
    case ActivityHero = 1;

    /** Event slot card with attached activity: age, type, filtered taxonomy tags. */
    case EventSlotCard = 2;

    /** Browse / search listing card: taxonomy tags only (all categories on activity). */
    case BrowseCard = 3;

    /** Event pending proposal row: compact tags and meta under activity title. */
    case EventProposal = 4;
}
