<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
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

        $payload = Cache::remember($cacheKey, 86400, function () use ($lat, $lng) {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Nerdik').' ('.config('app.url', 'http://localhost').')',
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
            $address = $data['address'] ?? [];

            $city = $address['city']
                ?? $address['town']
                ?? $address['village']
                ?? $address['municipality']
                ?? $address['city_district']
                ?? $address['county']
                ?? null;

            return [
                'display_name' => $data['display_name'] ?? null,
                'city' => $city,
                'country' => $address['country'] ?? null,
            ];
        });

        if ($payload === null) {
            return response()->json(['message' => __('Location lookup failed.')], 502);
        }

        return response()->json($payload);
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

        $results = Cache::remember($cacheKey, 86400, function () use ($q) {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Nerdik').' ('.config('app.url', 'http://localhost').')',
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => 6,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $rows = $response->json();
            if (! is_array($rows)) {
                return [];
            }

            return collect($rows)->map(function (array $item) {
                $address = $item['address'] ?? [];
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? null;

                return [
                    'label' => $item['display_name'] ?? '',
                    'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                    'lon' => isset($item['lon']) ? (float) $item['lon'] : null,
                    'city' => $city,
                    'country' => $address['country'] ?? null,
                ];
            })->filter(fn (array $r) => $r['lat'] !== null && $r['lon'] !== null && $r['label'] !== '')->values()->all();
        });

        return response()->json(['results' => $results]);
    }
}
