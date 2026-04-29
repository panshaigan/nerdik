<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Services\LocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    private const CACHE_TTL_SECONDS = 86400;

    private const HTTP_TIMEOUT_SECONDS = 8;

    private const SEARCH_RESULTS_LIMIT = 6;

    public function __construct(
        private readonly LocationResolver $locationResolver
    ) {}

    /**
     * Reverse geocode via Nominatim (cached). Used by the place map picker.
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function reverse(Request $request)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        $cacheKey = sprintf('geocode:reverse:%.5f:%.5f', $lat, $lng);

        $raw = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($lat, $lng) {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Nerdik').' ('.config('app.url', 'http://localhost').')',
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return [
                'display_name' => $data['display_name'] ?? null,
                'address' => $data['address'] ?? [],
            ];
        });

        if ($raw === null) {
            return response()->json(['message' => __('Location lookup failed.')], 502);
        }

        $address = $raw['address'] ?? [];
        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['city_district']
            ?? $address['county']
            ?? null;

        $resolved = $this->locationResolver->resolveFromNominatimAddress($address, $city);
        $addressShort = $this->shortAddressFromNominatimAddress($address);

        $loc = app()->getLocale();
        $country = Country::with('translations')->find($resolved['country_id']);
        $cityModel = City::with('translations')->find($resolved['city_id']);

        return response()->json([
            'display_name' => $raw['display_name'],
            'address_short' => $addressShort,
            'city' => $city,
            'country' => $address['country'] ?? null,
            'country_code' => isset($address['country_code']) ? strtoupper((string) $address['country_code']) : null,
            'country_id' => $resolved['country_id'],
            'city_id' => $resolved['city_id'],
            'city_display' => $cityModel?->name($loc) ?? $city,
            'country_display' => $country?->name($loc) ?? ($address['country'] ?? null),
        ]);
    }

    /**
     * Forward search (places/addresses) via Nominatim.
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
        ]);

        $q = trim($validated['q']);
        $cacheKey = 'geocode:search:'.md5(mb_strtolower($q));

        $rows = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($q) {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Nerdik').' ('.config('app.url', 'http://localhost').')',
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => self::SEARCH_RESULTS_LIMIT,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $rows = $response->json();

            return is_array($rows) ? $rows : [];
        });

        $loc = app()->getLocale();

        $results = collect($rows)->map(function (array $item) use ($loc) {
            $address = $item['address'] ?? [];
            $city = $address['city']
                ?? $address['town']
                ?? $address['village']
                ?? $address['municipality']
                ?? null;
            $addressShort = $this->shortAddressFromNominatimAddress($address);

            $resolved = $this->locationResolver->resolveFromNominatimAddress($address, $city);

            $country = Country::with('translations')->find($resolved['country_id']);
            $cityModel = City::with('translations')->find($resolved['city_id']);

            return [
                'label' => $item['display_name'] ?? '',
                'address_short' => $addressShort,
                'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                'lon' => isset($item['lon']) ? (float) $item['lon'] : null,
                'city' => $city,
                'country' => $address['country'] ?? null,
                'country_code' => isset($address['country_code']) ? strtoupper((string) $address['country_code']) : null,
                'country_id' => $resolved['country_id'],
                'city_id' => $resolved['city_id'],
                'city_display' => $cityModel?->name($loc) ?? $city,
                'country_display' => $country?->name($loc) ?? ($address['country'] ?? null),
            ];
        })->filter(fn (array $r) => $r['lat'] !== null && $r['lon'] !== null && $r['label'] !== '')->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * Build short address as "Street HouseNumber" when possible.
     *
     * @param  array<string, mixed>  $address
     */
    private function shortAddressFromNominatimAddress(array $address): ?string
    {
        $street = trim((string) (
            $address['road']
            ?? $address['pedestrian']
            ?? $address['footway']
            ?? $address['cycleway']
            ?? $address['path']
            ?? ''
        ));
        $houseNumber = trim((string) ($address['house_number'] ?? ''));

        if ($street === '') {
            return null;
        }

        return trim($street.' '.$houseNumber);
    }
}
