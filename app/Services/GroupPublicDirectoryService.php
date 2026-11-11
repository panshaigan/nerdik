<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Support\BrowseTagFilter;
use App\Support\Geo\BoundingBox;
use App\Support\Geo\Haversine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GroupPublicDirectoryService
{
    private const float EARTH_RADIUS_KM = Haversine::EARTH_RADIUS_KM;

    /**
     * @param  list<int>  $tagIds
     */
    public function discover(
        User $viewer,
        string $nameQuery = '',
        array $tagIds = [],
        bool $nearMyPlayArea = false,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $memberGroupIds = $viewer->groups()->pluck('groups.id')->all();

        $query = Group::query()
            ->publiclyDiscoverable()
            ->with(['tags.translations', 'city.translations'])
            ->withCount('users')
            ->when($memberGroupIds !== [], fn (Builder $q) => $q->whereNotIn('groups.id', $memberGroupIds));

        $trimmedName = trim($nameQuery);
        if ($trimmedName !== '') {
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('name', $operator, '%'.$trimmedName.'%');
        }

        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        if ($tagIds !== []) {
            BrowseTagFilter::apply($query, $tagIds, matchAll: false, relation: 'tags');
        }

        if ($nearMyPlayArea) {
            $viewer->loadMissing('playArea');
            $playArea = $viewer->playArea;
            if ($playArea === null) {
                return $query->whereRaw('1 = 0')->paginate($perPage);
            }

            $this->applyGroupsOverlappingPlayArea(
                $query,
                (float) $playArea->latitude,
                (float) $playArea->longitude,
                (int) $playArea->radius_km,
            );
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  Builder<Group>  $query
     */
    private function applyGroupsOverlappingPlayArea(
        Builder $query,
        float $userLat,
        float $userLng,
        int $userRadiusKm,
    ): void {
        $bboxRadius = $userRadiusKm + UserPlayPreferencesService::MAX_RADIUS_KM;
        $bbox = BoundingBox::fromCenterRadiusKm($userLat, $userLng, $bboxRadius);

        $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('radius_km')
            ->whereBetween('latitude', [(float) $bbox['min_lat'], (float) $bbox['max_lat']])
            ->whereBetween('longitude', [(float) $bbox['min_lng'], (float) $bbox['max_lng']])
            ->whereRaw(
                '(? * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= radius_km + ?',
                [self::EARTH_RADIUS_KM, $userLat, $userLng, $userLat, $userRadiusKm],
            );
    }
}
