<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Models\ActivityType;
use App\Models\Slot;
use Illuminate\Support\Collection;

/**
 * Builds locale-aware propose-noun phrases from free event slots' allowed activity types.
 */
final class EventProposalTypeCopy
{
    private const MAX_LISTED_NOUNS = 3;

    /**
     * @param  Collection<int, Slot>  $freeSlots
     */
    public function typesPhraseForFreeSlots(Collection $freeSlots): string
    {
        if ($freeSlots->isEmpty()) {
            return (string) __('ui.activities.propose_noun_fallback');
        }

        foreach ($freeSlots as $slot) {
            if ($this->slotAcceptsAnyActivityType($slot)) {
                return (string) __('ui.activities.propose_noun_fallback');
            }
        }

        $nouns = $this->distinctProposeNounsFromSlots($freeSlots);

        if ($nouns === []) {
            return (string) __('ui.activities.propose_noun_fallback');
        }

        return $this->joinNouns($nouns);
    }

    private function slotAcceptsAnyActivityType(Slot $slot): bool
    {
        if ($slot->relationLoaded('activityTypes')) {
            return $slot->getRelationValue('activityTypes')->isEmpty();
        }

        return $slot->activityTypes()->count() === 0;
    }

    /**
     * @param  Collection<int, Slot>  $freeSlots
     * @return list<string>
     */
    private function distinctProposeNounsFromSlots(Collection $freeSlots): array
    {
        $slugOrder = array_flip(ActivityType::slugs());
        $slugs = [];

        foreach ($freeSlots as $slot) {
            $types = $slot->relationLoaded('activityTypes')
                ? $slot->getRelationValue('activityTypes')
                : $slot->activityTypes;

            foreach ($types as $type) {
                $slug = $type->slug ?? null;
                if (is_string($slug) && $slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        $orderedSlugs = array_keys($slugs);
        usort($orderedSlugs, static fn (string $a, string $b): int => ($slugOrder[$a] ?? PHP_INT_MAX) <=> ($slugOrder[$b] ?? PHP_INT_MAX));

        $nouns = [];
        $seen = [];

        foreach ($orderedSlugs as $slug) {
            $noun = (string) __('ui.activities.propose_nouns.'.$slug);
            if ($noun === 'ui.activities.propose_nouns.'.$slug) {
                continue;
            }
            if (isset($seen[$noun])) {
                continue;
            }
            $seen[$noun] = true;
            $nouns[] = $noun;
        }

        return $nouns;
    }

    /**
     * @param  list<string>  $nouns
     */
    private function joinNouns(array $nouns): string
    {
        $total = count($nouns);

        if ($total === 1) {
            return $nouns[0];
        }

        if ($total === 2) {
            return (string) __('ui.events.propose_types_pair', [
                'first' => $nouns[0],
                'second' => $nouns[1],
            ]);
        }

        $hasOverflow = $total > self::MAX_LISTED_NOUNS;
        $listed = array_slice($nouns, 0, self::MAX_LISTED_NOUNS);

        $joined = (string) __('ui.events.propose_types_triple', [
            'first' => $listed[0],
            'second' => $listed[1],
            'third' => $listed[2],
        ]);

        if ($hasOverflow) {
            return trim($joined.' '.__('ui.events.propose_types_overflow'));
        }

        return $joined;
    }
}
