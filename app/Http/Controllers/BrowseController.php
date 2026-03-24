<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Tag;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    public function events(Request $request)
    {
        $query = Event::with(['organization', 'instances', 'creator', 'tags.translations'])
            ->where('is_public', true)
            ->orderBy('updated_at', 'desc');

        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id));
        }

        $events = $query->paginate(12);

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        $wishlistEventIds = auth()->check() ? auth()->user()->wishlistEvents()->pluck('events.id')->toArray() : [];

        return view('browse.events', compact('events', 'places', 'tags', 'wishlistEventIds'));
    }

    public function activities(Request $request)
    {
        $query = Activity::with(['host', 'tags.translations', 'slot.eventInstance.event'])
            ->whereHas('slot', fn ($q) => $q->whereHas('eventInstance.event', fn ($e) => $e->where('is_public', true)))
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
}
