<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use App\Services\LocationResolver;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly LocationResolver $locationResolver
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = Event::with('organization')
            ->orderBy('starts_at', 'desc')
            ->get();

        return view('events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $organizations = Organization::where('created_by', Auth::id())
            ->orderBy('name')
            ->get();
        $places = Place::with(['city.translations', 'country.translations'])
            ->orderBy('name')
            ->get();
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();

        return view('events.create', [
            'event' => new Event,
            'organizations' => $organizations,
            'places' => $places,
            'tags' => $tags,
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('created_by', Auth::id()))],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'place_ids' => ['nullable', 'array'],
            'place_ids.*' => ['integer', 'exists:places,id'],
            'new_places' => ['nullable', 'array'],
            'new_places.*.name' => ['nullable', 'string', 'max:255'],
            'new_places.*.city' => ['nullable', 'string', 'max:255'],
            'new_places.*.country' => ['nullable', 'string', 'max:255'],
            'new_places.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'new_places.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'new_places.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_places.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $tagIds = $validated['tag_ids'] ?? [];
        $placeIds = $validated['place_ids'] ?? [];
        unset($validated['tag_ids']);
        unset($validated['place_ids'], $validated['new_places']);

        // Slug is auto-generated in the model (from `name`).
        unset($validated['slug']);

        $event = Event::create($validated);
        $event->tags()->sync($tagIds);
        $this->syncEventPlaces($event, $placeIds, $request);

        return redirect()->route('events.index')
            ->with('status', __('Event created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $event->load(['creator', 'tags.translations', 'organization', 'slots.place', 'slots.activity']);
        $pendingProposals = $event->proposals()
            ->with(['activity', 'creator', 'proposedSlots'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $isOwner = auth()->check() && $event->created_by === auth()->id();

        return view('events.show', compact('event', 'pendingProposals', 'isOwner'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorizeCreatedBy($event);

        $organizations = Organization::where('created_by', Auth::id())
            ->orderBy('name')
            ->get();
        $places = Place::with(['city.translations', 'country.translations'])
            ->orderBy('name')
            ->get();
        $tags = Tag::with('translations')->orderBy('category')->orderBy('slug')->get();
        $event->load(['tags', 'places']);

        return view('events.edit', [
            'event' => $event,
            'organizations' => $organizations,
            'places' => $places,
            'tags' => $tags,
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($event->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $this->authorizeCreatedBy($event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('created_by', Auth::id()))],
            'desc' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'place_ids' => ['nullable', 'array'],
            'place_ids.*' => ['integer', 'exists:places,id'],
            'new_places' => ['nullable', 'array'],
            'new_places.*.name' => ['nullable', 'string', 'max:255'],
            'new_places.*.city' => ['nullable', 'string', 'max:255'],
            'new_places.*.country' => ['nullable', 'string', 'max:255'],
            'new_places.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'new_places.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'new_places.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_places.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $tagIds = $validated['tag_ids'] ?? [];
        $placeIds = $validated['place_ids'] ?? [];
        unset($validated['tag_ids']);
        unset($validated['place_ids'], $validated['new_places']);

        // Slug is auto-generated in the model (from `name`).
        unset($validated['slug']);

        $event->update($validated);
        $event->tags()->sync($tagIds);
        $this->syncEventPlaces($event, $placeIds, $request);

        return redirect()->route('events.index')
            ->with('status', __('Event updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $this->authorizeCreatedBy($event);

        $event->delete();

        return redirect()->route('events.index')
            ->with('status', __('Event deleted.'));
    }

    /**
     * Create a new event by copying an existing one:
     * - copies basic fields + tags
     * - copies slots, but clears activity_id (empty slots)
     */
    public function copy(Event $event)
    {
        if (! auth()->check()) {
            abort(403, __('Unauthorized.'));
        }

        $this->authorizeCreatedBy($event);

        $event->loadMissing(['tags', 'slots']);

        $newEvent = $event->replicate();
        $newEvent->name = $event->name.' (copy)';
        $newEvent->slug = null; // force auto-generation from name
        $newEvent->created_by = null; // let HasMetaColumns fill current user
        $newEvent->updated_by = null;
        $newEvent->save();

        $newEvent->tags()->sync($event->tags->pluck('id')->all());

        foreach ($event->slots()->get() as $slot) {
            $newEvent->slots()->create([
                'name' => $slot->name,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'place_id' => $slot->place_id,
                'requires_approval' => $slot->requires_approval,
                'max_capacity' => $slot->max_capacity,
                'activity_id' => null, // important: new event has empty slots
            ]);
        }

        return redirect()->route('events.show', $newEvent)
            ->with('status', __('Event copied.'));
    }

    protected function syncEventPlaces(Event $event, array $placeIds, Request $request): void
    {
        $placeIds = array_values(array_unique(array_map('intval', $placeIds)));

        foreach ($request->input('new_places', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                continue;
            }
            $resolved = $this->locationResolver->resolvePlaceRow($row);

            $newPlace = Place::create([
                'name' => $name,
                'type' => 'venue',
                'city_id' => $resolved['city_id'],
                'country_id' => $resolved['country_id'],
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'is_online' => false,
            ]);
            $placeIds[] = $newPlace->id;
        }

        $event->places()->sync(array_values(array_unique($placeIds)));
    }

    /**
     * @return list<string>
     */
    protected function nameSuggestionsForCurrentUser(?int $exceptEventId = null): array
    {
        $query = Event::query()
            ->where('created_by', Auth::id());

        if ($exceptEventId !== null) {
            $query->where('id', '!=', $exceptEventId);
        }

        return $query
            ->whereNotNull('name')
            ->orderBy('starts_at', 'desc')
            ->limit(40)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }
}
