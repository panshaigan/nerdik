<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Activity;
use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GeoJSON map payloads for /search (events + activities) with server-side clustering.
 */
final class BrowseMapFeatures
{
    public const CLUSTER_ZOOM_THRESHOLD = 11;

    /** At or below this zoom, show one marker per country (centroid of matching places). */
    public const COUNTRY_ROLLUP_MAX_ZOOM = 5;

    public const MAX_LAT_SPAN = 28.0;

    public const MAX_LNG_SPAN = 45.0;

    public const MAX_ROWS_PER_KIND = 3500;

    /**
     * @param  array<string, mixed>  $extraMeta
     * @return array{type: string, features: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function countryRollupResponse(BrowseListingFilterBag $bag, array $extraMeta = []): array
    {
        $merged = self::mergeCountryAggregates($bag);
        if ($merged === []) {
            return [
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => array_merge([
                    'aggregate' => 'country',
                    'clustered' => false,
                    'count' => 0,
                ], $extraMeta),
            ];
        }

        $countryIds = array_keys($merged);
        $countries = Country::query()
            ->with('translations')
            ->whereIn('id', $countryIds)
            ->get()
            ->keyBy('id');

        $features = [];
        $total = 0;
        foreach ($merged as $countryId => $row) {
            $total += $row['count'];
            $lat = $row['lat_sum'] / $row['count'];
            $lng = $row['lng_sum'] / $row['count'];
            if (! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }
            /** @var Country|null $country */
            $country = $countries->get($countryId);
            $label = $country !== null ? (string) $country->iso_alpha2 : '';
            $name = $country !== null ? (string) ($country->name() ?? $label) : '';

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'countrySummary' => true,
                    'country_id' => $countryId,
                    'country_iso' => $label,
                    'country_name' => $name,
                    'count' => $row['count'],
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => array_merge([
                'aggregate' => 'country',
                'clustered' => false,
                'count' => $total,
            ], $extraMeta),
        ];
    }

    /**
     * @return array<int, array{count: int, lat_sum: float, lng_sum: float}>
     */
    private static function mergeCountryAggregates(BrowseListingFilterBag $bag): array
    {
        $byCountry = [];

        if (! $bag->onlyActivities) {
            foreach (self::eventCountryAggregateRows($bag) as $r) {
                $cid = (int) $r->country_id;
                $c = (int) $r->listing_count;
                if ($cid <= 0 || $c <= 0) {
                    continue;
                }
                if (! isset($byCountry[$cid])) {
                    $byCountry[$cid] = ['count' => 0, 'lat_sum' => 0.0, 'lng_sum' => 0.0];
                }
                $lat = (float) $r->rep_lat;
                $lng = (float) $r->rep_lng;
                if (! is_finite($lat) || ! is_finite($lng)) {
                    continue;
                }
                $byCountry[$cid]['count'] += $c;
                $byCountry[$cid]['lat_sum'] += $lat * $c;
                $byCountry[$cid]['lng_sum'] += $lng * $c;
            }
        }

        if (! $bag->onlyEvents) {
            foreach (self::activityCountryAggregateRowsSelfHosted($bag) as $r) {
                self::accumulateCountryRow($byCountry, $r);
            }
            foreach (self::activityCountryAggregateRowsScheduled($bag) as $r) {
                self::accumulateCountryRow($byCountry, $r);
            }
        }

        return $byCountry;
    }

    /**
     * @param  array<int, array{count: int, lat_sum: float, lng_sum: float}>  $byCountry
     */
    private static function accumulateCountryRow(array &$byCountry, object $r): void
    {
        $cid = (int) $r->country_id;
        $c = (int) $r->listing_count;
        if ($cid <= 0 || $c <= 0) {
            return;
        }
        $lat = (float) $r->rep_lat;
        $lng = (float) $r->rep_lng;
        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (! isset($byCountry[$cid])) {
            $byCountry[$cid] = ['count' => 0, 'lat_sum' => 0.0, 'lng_sum' => 0.0];
        }
        $byCountry[$cid]['count'] += $c;
        $byCountry[$cid]['lat_sum'] += $lat * $c;
        $byCountry[$cid]['lng_sum'] += $lng * $c;
    }

    /**
     * @return Collection<int, object{country_id: mixed, listing_count: int|string, rep_lat: mixed, rep_lng: mixed}>
     */
    private static function eventCountryAggregateRows(BrowseListingFilterBag $bag): Collection
    {
        $q = BrowseListingQuery::baseEventQuery($bag);
        $q->join('event_place', 'event_place.event_id', '=', 'events.id')
            ->join('places', 'places.id', '=', 'event_place.place_id')
            ->whereNotNull('places.country_id')
            ->whereNotNull('places.latitude')
            ->whereNotNull('places.longitude')
            ->selectRaw('places.country_id as country_id')
            ->selectRaw('COUNT(DISTINCT events.id) as listing_count')
            ->selectRaw('AVG(places.latitude) as rep_lat')
            ->selectRaw('AVG(places.longitude) as rep_lng')
            ->groupBy('places.country_id');

        return $q->get();
    }

    /**
     * @return Collection<int, object{country_id: mixed, listing_count: int|string, rep_lat: mixed, rep_lng: mixed}>
     */
    private static function activityCountryAggregateRowsSelfHosted(BrowseListingFilterBag $bag): Collection
    {
        $q = BrowseListingQuery::baseActivityQuery($bag);
        $q->where('activities.hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->join('places', 'places.id', '=', 'activities.place_id')
            ->whereNotNull('places.country_id')
            ->whereNotNull('places.latitude')
            ->whereNotNull('places.longitude')
            ->selectRaw('places.country_id as country_id')
            ->selectRaw('COUNT(DISTINCT activities.id) as listing_count')
            ->selectRaw('AVG(places.latitude) as rep_lat')
            ->selectRaw('AVG(places.longitude) as rep_lng')
            ->groupBy('places.country_id');

        return $q->get();
    }

    /**
     * @return Collection<int, object{country_id: mixed, listing_count: int|string, rep_lat: mixed, rep_lng: mixed}>
     */
    private static function activityCountryAggregateRowsScheduled(BrowseListingFilterBag $bag): Collection
    {
        $firstSlot = DB::table('slots')
            ->selectRaw('activity_id')
            ->selectRaw('MIN(id) as sid')
            ->whereNotNull('event_id')
            ->groupBy('activity_id');

        $q = BrowseListingQuery::baseActivityQuery($bag);
        $q->where('activities.hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
            ->joinSub($firstSlot, 'fs', 'fs.activity_id', '=', 'activities.id')
            ->join('slots as slot_pick', 'slot_pick.id', '=', 'fs.sid')
            ->join('places', 'places.id', '=', 'slot_pick.place_id')
            ->whereNotNull('places.country_id')
            ->whereNotNull('places.latitude')
            ->whereNotNull('places.longitude')
            ->selectRaw('places.country_id as country_id')
            ->selectRaw('COUNT(DISTINCT activities.id) as listing_count')
            ->selectRaw('AVG(places.latitude) as rep_lat')
            ->selectRaw('AVG(places.longitude) as rep_lng')
            ->groupBy('places.country_id');

        return $q->get();
    }

    /**
     * @return array{type: string, features: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function geoJson(BrowseListingFilterBag $bag, int $zoom): array
    {
        if ($zoom <= self::COUNTRY_ROLLUP_MAX_ZOOM) {
            return self::countryRollupResponse($bag);
        }

        $rows = self::listingRows($bag);

        if ($rows->isEmpty()) {
            return [
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => [
                    'aggregate' => 'none',
                    'clustered' => false,
                    'count' => 0,
                ],
            ];
        }

        if ($zoom >= self::CLUSTER_ZOOM_THRESHOLD) {
            return [
                'type' => 'FeatureCollection',
                'features' => $rows->map(fn (object $r) => self::pointFeature($r))->values()->all(),
                'meta' => [
                    'aggregate' => 'points',
                    'clustered' => false,
                    'count' => $rows->count(),
                ],
            ];
        }

        $factor = self::gridFactor($zoom);
        $buckets = [];

        foreach ($rows as $r) {
            $lat = (float) $r->rep_lat;
            $lng = (float) $r->rep_lng;
            if (! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }
            $gx = (int) floor($lat * $factor);
            $gy = (int) floor($lng * $factor);
            $key = $gx.':'.$gy;
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['count' => 0, 'lat_sum' => 0.0, 'lng_sum' => 0.0];
            }
            $buckets[$key]['count']++;
            $buckets[$key]['lat_sum'] += $lat;
            $buckets[$key]['lng_sum'] += $lng;
        }

        $features = [];
        foreach ($buckets as $bucket) {
            $n = $bucket['count'];
            $clat = $bucket['lat_sum'] / $n;
            $clng = $bucket['lng_sum'] / $n;
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$clng, $clat],
                ],
                'properties' => [
                    'cluster' => true,
                    'count' => $n,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'meta' => [
                'aggregate' => 'grid',
                'clustered' => true,
                'count' => $rows->count(),
                'grid_factor' => $factor,
            ],
        ];
    }

    /**
     * @return Collection<int, object{id: int|string, kind: string, name: string, slug: string, rep_lat: mixed, rep_lng: mixed}>
     */
    public static function listingRows(BrowseListingFilterBag $bag): Collection
    {
        $out = collect();

        if (! $bag->onlyActivities) {
            $q = BrowseListingQuery::baseEventQuery($bag);
            $q->select('events.id', 'events.name', 'events.slug');
            $q->selectRaw('(SELECT AVG(places.latitude) FROM event_place INNER JOIN places ON places.id = event_place.place_id WHERE event_place.event_id = events.id AND places.latitude IS NOT NULL AND places.longitude IS NOT NULL) as rep_lat');
            $q->selectRaw('(SELECT AVG(places.longitude) FROM event_place INNER JOIN places ON places.id = event_place.place_id WHERE event_place.event_id = events.id AND places.latitude IS NOT NULL AND places.longitude IS NOT NULL) as rep_lng');
            $q->limit(self::MAX_ROWS_PER_KIND);
            $out = $out->merge($q->get()->map(function ($row) {
                return (object) [
                    'id' => $row->id,
                    'kind' => 'event',
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'rep_lat' => $row->rep_lat,
                    'rep_lng' => $row->rep_lng,
                ];
            }));
        }

        if (! $bag->onlyEvents) {
            $sh = Activity::HOSTING_MODE_SELF_HOSTED;
            $se = Activity::HOSTING_MODE_SCHEDULED_ON_EVENT;
            $q = BrowseListingQuery::baseActivityQuery($bag);
            $q->select('activities.id', 'activities.name', 'activities.slug');
            $q->selectRaw("(CASE WHEN activities.hosting_mode = {$sh} THEN (SELECT places.latitude FROM places WHERE places.id = activities.place_id) WHEN activities.hosting_mode = {$se} THEN (SELECT places.latitude FROM slots INNER JOIN places ON places.id = slots.place_id WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1) END) as rep_lat");
            $q->selectRaw("(CASE WHEN activities.hosting_mode = {$sh} THEN (SELECT places.longitude FROM places WHERE places.id = activities.place_id) WHEN activities.hosting_mode = {$se} THEN (SELECT places.longitude FROM slots INNER JOIN places ON places.id = slots.place_id WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1) END) as rep_lng");
            $q->limit(self::MAX_ROWS_PER_KIND);
            $out = $out->merge($q->get()->map(function ($row) {
                return (object) [
                    'id' => $row->id,
                    'kind' => 'activity',
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'rep_lat' => $row->rep_lat,
                    'rep_lng' => $row->rep_lng,
                ];
            }));
        }

        return $out->filter(function (object $r): bool {
            $lat = is_numeric($r->rep_lat) ? (float) $r->rep_lat : null;
            $lng = is_numeric($r->rep_lng) ? (float) $r->rep_lng : null;

            return $lat !== null && $lng !== null && is_finite($lat) && is_finite($lng);
        })->values();
    }

    private static function gridFactor(int $zoom): float
    {
        return match (true) {
            $zoom <= 4 => 8.0,
            $zoom <= 6 => 24.0,
            $zoom <= 8 => 72.0,
            $zoom <= 9 => 144.0,
            default => 288.0,
        };
    }

    /**
     * @param  object{id: int|string, kind: string, name: string, slug: string, rep_lat: mixed, rep_lng: mixed}  $r
     * @return array<string, mixed>
     */
    private static function pointFeature(object $r): array
    {
        $lat = (float) $r->rep_lat;
        $lng = (float) $r->rep_lng;
        $url = $r->kind === 'event'
            ? route('events.show', ['event' => $r->slug])
            : route('activities.show', ['activity' => $r->slug]);

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => [
                'cluster' => false,
                'kind' => $r->kind,
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'slug' => (string) $r->slug,
                'url' => $url,
            ],
        ];
    }
}
