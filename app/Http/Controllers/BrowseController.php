<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Event;
use App\Models\EventInstance;
use App\Models\Place;
use App\Models\Tag;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    public function events(Request $request)
    {
        $query = Event::with('organization')
            ->where('is_public', true)
            ->orderBy('updated_at', 'desc');

        // Optional: add place filter when event_instance has places
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id));
        }

        $events = $query->paginate(12);

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        return view('browse.events', compact('events', 'places', 'tags'));
    }

    public function activities(Request $request)
    {
        $query = Activity::with(['host', 'slot.eventInstance.event'])
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

        $activities = $query->paginate(12);

        $places = Place::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('category')->get();

        return view('browse.activities', compact('activities', 'places', 'tags'));
    }
}
