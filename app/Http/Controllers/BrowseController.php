<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    public function events(Request $request)
    {
        $query = Event::with(['organization', 'creator', 'tags.translations'])
            ->where('is_public', true)
            ->orderBy('starts_at', 'desc');

        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id));
        }

        if ($request->filled(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $minLat = (float) $request->min_lat;
            $maxLat = (float) $request->max_lat;
            $minLng = (float) $request->min_lng;
            $maxLng = (float) $request->max_lng;
            if ($minLat > $maxLat) {
                [$minLat, $maxLat] = [$maxLat, $minLat];
            }
            if ($minLng > $maxLng) {
                [$minLng, $maxLng] = [$maxLng, $minLng];
            }
            $query->whereHas('places', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
            });
        }

        $events = $query->paginate(12)->withQueryString();

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        $wishlistEventIds = auth()->check() ? auth()->user()->wishlistEvents()->pluck('events.id')->toArray() : [];

        return view('browse.events', compact('events', 'places', 'tags', 'wishlistEventIds'));
    }

    public function activities(Request $request)
    {
        $query = Activity::with(['host', 'tags.translations', 'slot.event'])
            ->whereHas('slot', fn ($q) => $q->whereHas('event', fn ($e) => $e->where('is_public', true)))
            ->orderBy('updated_at', 'desc');

        if ($request->filled('from_date')) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '>=', $request->from_date));
        }
        if ($request->filled('to_date')) {
            $query->whereHas('slot', fn ($q) => $q->whereDate('starts_at', '<=', $request->to_date));
        }
        if ($request->filled('place_id')) {
            $query->whereHas('slot', fn ($q) => $q->where('place_id', $request->place_id));
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where('name', 'like', $term);
        }

        $activities = $query->paginate(12);

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        $wishlistActivityIds = auth()->check() ? auth()->user()->wishlistActivities()->pluck('activities.id')->toArray() : [];

        return view('browse.activities', compact('activities', 'places', 'tags', 'wishlistActivityIds'));
    }

    public function organizations(Request $request)
    {
        $query = Organization::with('creator')
            ->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }

        $organizations = $query->paginate(12);

        return view('browse.organizations', compact('organizations'));
    }
}
