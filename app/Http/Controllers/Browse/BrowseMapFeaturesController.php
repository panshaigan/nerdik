<?php

declare(strict_types=1);

namespace App\Http\Controllers\Browse;

use App\Http\Controllers\Controller;
use App\Support\Browse\BrowseListingFilterBag;
use App\Support\Browse\BrowseMapFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowseMapFeaturesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $bag = BrowseListingFilterBag::fromRequest($request);
        $zoom = (int) $request->query('zoom', 10);
        $zoom = max(0, min(22, $zoom));

        if (! $bag->hasBBox()) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => [
                    'invalidBBox' => true,
                ],
            ], 422);
        }

        $span = $bag->bboxSpan();
        if ($span['latSpan'] > BrowseMapFeatures::MAX_LAT_SPAN || $span['lngSpan'] > BrowseMapFeatures::MAX_LNG_SPAN) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'meta' => [
                    'bboxTooLarge' => true,
                    'latSpan' => $span['latSpan'],
                    'lngSpan' => $span['lngSpan'],
                ],
            ]);
        }

        $payload = BrowseMapFeatures::geoJson($bag, $zoom);

        return response()->json($payload);
    }
}
