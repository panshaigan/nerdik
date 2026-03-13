<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $places = Place::with('parent')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('places.index', compact('places'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $parents = Place::orderBy('name')->get();

        return view('places.create', [
            'place' => new Place(),
            'parents' => $parents,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:country,state,city,venue,room'],
            'parent_id' => ['nullable', 'exists:places,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:places,slug'],
            'links' => ['nullable', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'is_online' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $validated['is_online'] = $request->boolean('is_online');

        Place::create($validated);

        return redirect()->route('places.index')
            ->with('status', __('Place created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Place $place)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Place $place)
    {
        $parents = Place::where('id', '!=', $place->id)
            ->orderBy('name')
            ->get();

        return view('places.edit', compact('place', 'parents'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Place $place)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:country,state,city,venue,room'],
            'parent_id' => ['nullable', 'exists:places,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:places,slug,' . $place->id],
            'links' => ['nullable', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
            'is_online' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $validated['is_online'] = $request->boolean('is_online');

        $place->update($validated);

        return redirect()->route('places.index')
            ->with('status', __('Place updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Place $place)
    {
        $place->delete();

        return redirect()->route('places.index')
            ->with('status', __('Place deleted.'));
    }
}
