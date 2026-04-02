<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use App\Services\LocationResolver;
use App\Services\TagSelectionService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly LocationResolver $locationResolver,
        private readonly TagSelectionService $tagSelectionService
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
        $places = Place::with(['city.translations', 'country.translations'])
            ->orderBy('name')
            ->get();
        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();

        return view('events.create', [
            'event' => new Event,
            'places' => $places,
            'tags' => $tags,
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser(),
            'organizationSuggestions' => $this->organizationSuggestionsForCurrentUser(),
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
            'organization_name' => ['nullable', 'string', 'max:255'],
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
            'new_places.*.address' => ['nullable', 'string', 'max:500'],
            'new_places.*.city' => ['nullable', 'string', 'max:255'],
            'new_places.*.country' => ['nullable', 'string', 'max:255'],
            'new_places.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'new_places.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'new_places.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_places.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $validated['organization_id'] = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $validated['organization_name'] ?? null
        );
        unset($validated['organization_name']);

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tags'] ?? []
        );
        $placeIds = $validated['place_ids'] ?? [];
        unset($validated['tag_ids']);
        unset($validated['place_ids'], $validated['new_places'], $validated['new_tags']);

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
        $event->load(['creator', 'tags.translations', 'organization', 'places', 'slots.place', 'slots.activity']);
        $places = Place::orderBy('name')->get();
        $pendingProposals = $event->proposals()
            ->with(['activity', 'creator', 'proposedSlots'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $isOwner = auth()->check() && $event->created_by === auth()->id();

        return view('events.show', compact('event', 'places', 'pendingProposals', 'isOwner'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorizeCreatedBy($event);

        $places = Place::with(['city.translations', 'country.translations'])
            ->orderBy('name')
            ->get();
        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $event->load(['tags', 'places', 'organization']);

        return view('events.edit', [
            'event' => $event,
            'places' => $places,
            'tags' => $tags,
            'nameSuggestions' => $this->nameSuggestionsForCurrentUser($event->id),
            'organizationSuggestions' => $this->organizationSuggestionsForCurrentUser(),
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
            'organization_name' => ['nullable', 'string', 'max:255'],
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
            'new_places.*.address' => ['nullable', 'string', 'max:500'],
            'new_places.*.city' => ['nullable', 'string', 'max:255'],
            'new_places.*.country' => ['nullable', 'string', 'max:255'],
            'new_places.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'new_places.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'new_places.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'new_places.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ]);

        $validated['organization_id'] = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $validated['organization_name'] ?? null
        );
        unset($validated['organization_name']);

        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['starts_at'] = parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString();
        $validated['ends_at'] = parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString();

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tags'] ?? []
        );
        $placeIds = $validated['place_ids'] ?? [];
        unset($validated['tag_ids']);
        unset($validated['place_ids'], $validated['new_places'], $validated['new_tags']);

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
            $address = trim((string) ($row['address'] ?? ''));

            $newPlace = Place::create([
                'name' => $name,
                'address' => $address !== '' ? $address : null,
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

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function organizationSuggestionsForCurrentUser(): array
    {
        return Organization::query()
            ->where('created_by', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $org) => ['id' => $org->id, 'name' => $org->name])
            ->values()
            ->all();
    }

    protected function resolveOrganizationIdFromRequest(mixed $organizationId, ?string $organizationName): ?int
    {
        $id = $organizationId;
        if ($id === null || $id === '') {
            $id = null;
        } else {
            $id = (int) $id;
        }

        if ($id !== null) {
            $exists = Organization::query()
                ->where('id', $id)
                ->where('created_by', Auth::id())
                ->exists();

            if ($exists) {
                return $id;
            }
        }

        $name = trim((string) $organizationName);
        if ($name === '') {
            return null;
        }

        return $this->findOrCreateOrganizationForUser($name)->id;
    }

    protected function findOrCreateOrganizationForUser(string $name): Organization
    {
        $userId = Auth::id();

        $existing = Organization::query()
            ->where('created_by', $userId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Organization::create([
            'name' => $name,
        ]);
    }
}
