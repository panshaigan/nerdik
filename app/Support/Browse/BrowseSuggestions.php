<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class BrowseSuggestions
{
    /**
     * @return array{
     *     tags: list<array<string, mixed>>,
     *     events: list<array{id: int, label: string, url: string}>,
     *     activities: list<array{id: int, label: string, url: string}>
     * }
     */
    public static function search(
        string $query,
        bool $includePastEvents,
        ?int $userId,
        bool $includeEvents = true,
        bool $includeActivities = true,
    ): array {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [
                'tags' => [],
                'events' => [],
                'activities' => [],
            ];
        }

        $locale = app()->getLocale();
        $categoryContext = self::categoryContext($locale);
        $tagLimit = (int) config('browse.tag_suggestions.search_limit', 30);
        $listingLimit = (int) config('browse.listing_suggestions.limit', 5);

        $tags = Tag::query()->searchForBrowseSelector($trimmed, $includePastEvents, $tagLimit);

        $events = $includeEvents
            ? self::searchEvents($trimmed, $includePastEvents, $userId, $listingLimit)
            : [];

        $activities = $includeActivities
            ? self::searchActivities($trimmed, $includePastEvents, $userId, $listingLimit)
            : [];

        return [
            'tags' => BrowseTagSelectorPayload::fromCollection(
                $tags,
                $locale,
                $categoryContext['namesById'],
                $categoryContext['keysById'],
                includeRelatedIds: false,
            ),
            'events' => $events,
            'activities' => $activities,
        ];
    }

    /**
     * @return array{namesById: array<int, string>, keysById: array<int, string>}
     */
    public static function categoryContext(string $locale): array
    {
        $cacheKey = 'browse.tag_selector.category_maps.'.$locale;

        return Cache::remember($cacheKey, 3600, function () use ($locale): array {
            $categories = BrowseTagSelectorPayload::categoriesForLocale($locale);
            $maps = BrowseTagSelectorPayload::categoryMapsFromConfig($categories);

            return [
                'namesById' => $maps['namesById'],
                'keysById' => $maps['keysById'],
            ];
        });
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    private static function searchEvents(
        string $query,
        bool $includePastEvents,
        ?int $userId,
        int $limit,
    ): array {
        $normalized = mb_strtolower(trim($query));
        $like = '%'.$normalized.'%';
        $prefix = $normalized.'%';

        $filters = new BrowseListingFilterBag(
            q: '',
            tagIds: [],
            tagsMatchAll: false,
            includePastEvents: $includePastEvents,
            onlyEvents: false,
            onlyActivities: false,
            onlyMine: false,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );

        $builder = BrowseListingQuery::baseEventQuery($filters, $userId)
            ->select(['events.id', 'events.name', 'events.slug'])
            ->where(function (Builder $outer) use ($like, $normalized): void {
                $outer->whereRaw('unaccent(LOWER(events.name)) LIKE unaccent(LOWER(?))', [$like])
                    ->orWhereRaw('unaccent(LOWER(COALESCE(events.description, \'\'))) LIKE unaccent(LOWER(?))', [$like]);
                if (mb_strlen($normalized) >= 3) {
                    $outer->orWhereRaw(
                        'similarity(unaccent(LOWER(events.name)), unaccent(LOWER(?))) >= ?',
                        [$normalized, 0.15]
                    );
                }
            })
            ->orderByRaw(
                '
                CASE
                    WHEN unaccent(LOWER(events.name)) = unaccent(LOWER(?)) THEN 0
                    WHEN unaccent(LOWER(events.name)) LIKE unaccent(LOWER(?)) THEN 1
                    ELSE 2
                END ASC,
                events.name ASC
                ',
                [$normalized, $prefix]
            )
            ->limit($limit);

        return $builder->get()->map(static fn (Event $event): array => [
            'id' => (int) $event->id,
            'label' => (string) $event->name,
            'url' => route('events.show', $event),
        ])->all();
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    private static function searchActivities(
        string $query,
        bool $includePastEvents,
        ?int $userId,
        int $limit,
    ): array {
        $normalized = mb_strtolower(trim($query));
        $like = '%'.$normalized.'%';
        $prefix = $normalized.'%';

        $filters = new BrowseListingFilterBag(
            q: '',
            tagIds: [],
            tagsMatchAll: false,
            includePastEvents: $includePastEvents,
            onlyEvents: false,
            onlyActivities: false,
            onlyMine: false,
            minLat: null,
            maxLat: null,
            minLng: null,
            maxLng: null,
        );

        $builder = BrowseListingQuery::baseActivityQuery($filters, $userId)
            ->select(['activities.id', 'activities.name', 'activities.slug'])
            ->where(function (Builder $outer) use ($like, $normalized): void {
                $outer->whereRaw('unaccent(LOWER(activities.name)) LIKE unaccent(LOWER(?))', [$like])
                    ->orWhereRaw('unaccent(LOWER(COALESCE(activities.description, \'\'))) LIKE unaccent(LOWER(?))', [$like]);
                if (mb_strlen($normalized) >= 3) {
                    $outer->orWhereRaw(
                        'similarity(unaccent(LOWER(activities.name)), unaccent(LOWER(?))) >= ?',
                        [$normalized, 0.15]
                    );
                }
            })
            ->orderByRaw(
                '
                CASE
                    WHEN unaccent(LOWER(activities.name)) = unaccent(LOWER(?)) THEN 0
                    WHEN unaccent(LOWER(activities.name)) LIKE unaccent(LOWER(?)) THEN 1
                    ELSE 2
                END ASC,
                activities.name ASC
                ',
                [$normalized, $prefix]
            )
            ->limit($limit);

        return $builder->get()->map(static fn (Activity $activity): array => [
            'id' => (int) $activity->id,
            'label' => (string) $activity->name,
            'url' => route('activities.show', $activity),
        ])->all();
    }
}
