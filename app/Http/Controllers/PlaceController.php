<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PlaceController extends Controller
{
    use AuthorizesOwnership;

    /**
     * @return array{countries: Collection, cities: Collection}
     */
    protected function geoForForm(): array
    {
        $locale = app()->getLocale();
        $countries = Country::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Country $c) => mb_strtolower((string) ($c->name($locale) ?? '')));

        $cities = City::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (City $c) => mb_strtolower((string) ($c->name($locale) ?? '')));

        return compact('countries', 'cities');
    }

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
            'place' => new Place,
            'parents' => $parents,
            ...$this->geoForForm(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'type' => ['required', 'in:state,venue,room'],
            'parent_id' => ['nullable', 'exists:places,id'],
            'links' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_online' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $validated['is_online'] = $request->boolean('is_online');

        $this->alignCityCountry($validated);

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
        $this->authorizeCreatedBy($place);

        $parents = Place::where('id', '!=', $place->id)
            ->orderBy('name')
            ->get();

        return view('places.edit', [
            'place' => $place,
            'parents' => $parents,
            ...$this->geoForForm(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Place $place)
    {
        $this->authorizeCreatedBy($place);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'type' => ['required', 'in:state,venue,room'],
            'parent_id' => ['nullable', 'exists:places,id'],
            'links' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_online' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $validated['is_online'] = $request->boolean('is_online');

        $this->alignCityCountry($validated);

        $place->update($validated);

        return redirect()->route('places.index')
            ->with('status', __('Place updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Place $place)
    {
        $this->authorizeCreatedBy($place);

        $place->delete();

        return redirect()->route('places.index')
            ->with('status', __('Place deleted.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function alignCityCountry(array &$validated): void
    {
        $cityId = $validated['city_id'] ?? null;
        $countryId = $validated['country_id'] ?? null;
        if (! $cityId) {
            return;
        }

        $city = City::query()->find($cityId);
        if (! $city) {
            return;
        }

        if (! $countryId || (int) $city->country_id !== (int) $countryId) {
            $validated['country_id'] = $city->country_id;
        }
    }
}
