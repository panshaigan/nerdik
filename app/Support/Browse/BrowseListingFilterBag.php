<?php

declare(strict_types=1);

namespace App\Support\Browse;

use Illuminate\Http\Request;

/**
 * Immutable snapshot of browse /search listing filters (shared by Livewire and map API).
 */
final class BrowseListingFilterBag
{
    /**
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public readonly string $q,
        public readonly array $tagIds,
        public readonly bool $tagsMatchAll,
        public readonly bool $includePastEvents,
        public readonly bool $onlyEvents,
        public readonly bool $onlyActivities,
        public readonly bool $onlyMine,
        public readonly ?string $minLat,
        public readonly ?string $maxLat,
        public readonly ?string $minLng,
        public readonly ?string $maxLng,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $tagIds = $request->input('tag_ids', []);
        if (! is_array($tagIds)) {
            $tagIds = [];
        }

        $normalizedTagIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tagIds),
            static fn (int $id) => $id > 0
        )));

        $onlyEvents = filter_var($request->input('only_events', false), FILTER_VALIDATE_BOOLEAN);
        $onlyActivities = filter_var($request->input('only_activities', false), FILTER_VALIDATE_BOOLEAN);
        if ($onlyEvents && $onlyActivities) {
            $winner = BrowseSearchUrl::resolveExclusiveKindFilterFromRequest($request);
            $onlyEvents = $winner === 'events';
            $onlyActivities = $winner === 'activities';
        }

        return new self(
            q: trim((string) $request->input('q', '')),
            tagIds: $normalizedTagIds,
            tagsMatchAll: filter_var($request->input('tags_match_all', false), FILTER_VALIDATE_BOOLEAN),
            includePastEvents: filter_var($request->input('include_past_events', false), FILTER_VALIDATE_BOOLEAN),
            onlyEvents: $onlyEvents,
            onlyActivities: $onlyActivities,
            onlyMine: filter_var($request->input('only_mine', false), FILTER_VALIDATE_BOOLEAN),
            minLat: self::nullableString($request->input('min_lat')),
            maxLat: self::nullableString($request->input('max_lat')),
            minLng: self::nullableString($request->input('min_lng')),
            maxLng: self::nullableString($request->input('max_lng')),
        );
    }

    public function hasBBox(): bool
    {
        if (! filled($this->minLat) || ! filled($this->maxLat)
            || ! filled($this->minLng) || ! filled($this->maxLng)) {
            return false;
        }

        foreach ([$this->minLat, $this->maxLat, $this->minLng, $this->maxLng] as $v) {
            if (! is_numeric($v)) {
                return false;
            }
        }

        $minLat = (float) $this->minLat;
        $maxLat = (float) $this->maxLat;
        $minLng = (float) $this->minLng;
        $maxLng = (float) $this->maxLng;

        if (! is_finite($minLat) || ! is_finite($maxLat) || ! is_finite($minLng) || ! is_finite($maxLng)) {
            return false;
        }

        if ($minLat < -90.0 || $maxLat > 90.0 || $minLng < -180.0 || $maxLng > 180.0) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function normalizedBBox(): array
    {
        $minLat = (float) $this->minLat;
        $maxLat = (float) $this->maxLat;
        $minLng = (float) $this->minLng;
        $maxLng = (float) $this->maxLng;
        if ($minLat > $maxLat) {
            [$minLat, $maxLat] = [$maxLat, $minLat];
        }
        if ($minLng > $maxLng) {
            [$minLng, $maxLng] = [$maxLng, $minLng];
        }

        return [$minLat, $maxLat, $minLng, $maxLng];
    }

    /**
     * @return array{latSpan: float, lngSpan: float}
     */
    public function bboxSpan(): array
    {
        [$minLat, $maxLat, $minLng, $maxLng] = $this->normalizedBBox();

        return [
            'latSpan' => abs($maxLat - $minLat),
            'lngSpan' => abs($maxLng - $minLng),
        ];
    }

    /**
     * Same filters without geographic bounds (used for map summaries when the viewport is too large).
     */
    public function withoutBBox(): self
    {
        return new self(
            q: $this->q,
            tagIds: $this->tagIds,
            tagsMatchAll: $this->tagsMatchAll,
            includePastEvents: $this->includePastEvents,
            onlyEvents: $this->onlyEvents,
            onlyActivities: $this->onlyActivities,
            onlyMine: $this->onlyMine,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );
    }

    private static function nullableString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_string($v) ? $v : (string) $v;
    }
}
