<?php

declare(strict_types=1);

namespace App\Support\Search;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use App\Support\Catalog\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class GlobalNavSearch
{
    private const MIN_QUERY_LENGTH = 2;

    private const PER_GROUP_LIMIT = 5;

    /**
     * @return array{
     *     events: list<array{kind: string, id: int, label: string, url: string}>,
     *     activities: list<array{kind: string, id: int, label: string, url: string}>,
     *     organizations: list<array{kind: string, id: int, label: string, url: null}>,
     *     users: list<array{kind: string, id: int, label: string, url: null}>,
     *     places: list<array{kind: string, id: int, label: string, url: string}>,
     *     tags: list<array{kind: string, id: int, label: string, url: string}>
     * }
     */
    public static function search(string $query, ?User $user): array
    {
        $trimmed = trim($query);
        if (mb_strlen($trimmed) < self::MIN_QUERY_LENGTH) {
            return self::emptyPayload();
        }

        $limit = self::PER_GROUP_LIMIT;

        return [
            'events' => self::searchEvents($trimmed, $user, $limit),
            'activities' => self::searchActivities($trimmed, $user, $limit),
            'organizations' => self::searchOrganizations($trimmed, $limit),
            'users' => self::searchUsers($trimmed, $limit),
            'places' => self::searchPlaces($trimmed, $limit),
            'tags' => self::searchTags($trimmed, $user, $limit),
        ];
    }

    /**
     * @return array{
     *     events: list<array{kind: string, id: int, label: string, url: string}>,
     *     activities: list<array{kind: string, id: int, label: string, url: string}>,
     *     organizations: list<array{kind: string, id: int, label: string, url: null}>,
     *     users: list<array{kind: string, id: int, label: string, url: null}>,
     *     places: list<array{kind: string, id: int, label: string, url: string}>,
     *     tags: list<array{kind: string, id: int, label: string, url: string}>
     * }
     */
    public static function emptyPayload(): array
    {
        return [
            'events' => [],
            'activities' => [],
            'organizations' => [],
            'users' => [],
            'places' => [],
            'tags' => [],
        ];
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: string}>
     */
    private static function searchEvents(string $query, ?User $user, int $limit): array
    {
        $normalized = mb_strtolower(trim($query));
        $like = '%'.$normalized.'%';
        $prefix = $normalized.'%';

        $builder = Event::query()
            ->whereNull('events.cancelled_at')
            ->visibleTo($user)
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
            'kind' => 'event',
            'id' => (int) $event->id,
            'label' => (string) $event->name,
            'url' => route('events.show', $event),
        ])->all();
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: string}>
     */
    private static function searchActivities(string $query, ?User $user, int $limit): array
    {
        $normalized = mb_strtolower(trim($query));
        $like = '%'.$normalized.'%';
        $prefix = $normalized.'%';

        $builder = Activity::query()
            ->whereNull('activities.cancelled_at')
            ->visibleTo($user)
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
            'kind' => 'activity',
            'id' => (int) $activity->id,
            'label' => (string) $activity->name,
            'url' => route('activities.show', $activity),
        ])->all();
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: null}>
     */
    private static function searchOrganizations(string $query, int $limit): array
    {
        return CatalogQuery::organizations($query)
            ->limit($limit)
            ->get()
            ->map(static fn (Organization $organization): array => [
                'kind' => 'organization',
                'id' => (int) $organization->id,
                'label' => (string) $organization->name,
                'url' => null,
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: null}>
     */
    private static function searchUsers(string $query, int $limit): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return User::query()
            ->where('is_deleted', false)
            ->where('nickname', $operator, '%'.$term.'%')
            ->orderBy('nickname')
            ->limit($limit)
            ->get()
            ->map(static fn (User $user): array => [
                'kind' => 'user',
                'id' => (int) $user->id,
                'label' => $user->displayName(),
                'url' => null,
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: string}>
     */
    private static function searchPlaces(string $query, int $limit): array
    {
        return CatalogQuery::places($query)
            ->limit($limit)
            ->get()
            ->map(static fn (Place $place): array => [
                'kind' => 'place',
                'id' => (int) $place->id,
                'label' => (string) $place->name,
                'url' => BrowseSearchUrl::forPlace($place),
            ])
            ->all();
    }

    /**
     * @return list<array{kind: string, id: int, label: string, url: string}>
     */
    private static function searchTags(string $query, ?User $user, int $limit): array
    {
        return Tag::query()
            ->searchForGlobalNav($query, $user, $limit)
            ->map(static fn (Tag $tag): array => [
                'kind' => 'tag',
                'id' => (int) $tag->id,
                'label' => $tag->displayLabel(),
                'url' => route('search.index', ['tag_ids' => [(int) $tag->id]], false) ?: '/search?tag_ids[]='.(int) $tag->id,
            ])
            ->all();
    }
}
