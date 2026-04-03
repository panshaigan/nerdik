<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Services\TagSelectionService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly TagSelectionService $tagSelectionService
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $lockedEvent = null;
        if ($request->filled('event')) {
            $lockedEvent = Event::where('slug', $request->string('event'))->firstOrFail();
        }

        $events = Event::orderBy('starts_at', 'desc')->get();

        $slot = new Slot;
        if ($lockedEvent) {
            $slot->event_id = $lockedEvent->id;
        }

        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        if ($lockedEvent) {
            $slotMassVenues = $this->venuesForEventMassForm($lockedEvent);
            $slotMassRoomsByVenueId = $this->roomOptionsByVenueId($slotMassVenues);
        }

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
        $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());
        $massPlaceData = $this->slotMassFormPlaceDataForAllEvents();

        return view('slots.create', [
            'slot' => $slot,
            'events' => $events,
            'lockedEvent' => $lockedEvent,
            'tags' => $tags,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
        ] + $massPlaceData);
    }

    /**
     * Venues and room lists for the mass-create form when the event is not locked.
     *
     * @return array{eventVenuesByEventId: array<int, list<array{id:int,name:string,type:string}>>, roomsByEventAndVenue: array<int, array<int, list<array{id:int,name:string}>>>}
     */
    protected function slotMassFormPlaceDataForAllEvents(): array
    {
        $events = Event::with(['places' => fn ($q) => $q->orderBy('name')])
            ->orderBy('starts_at', 'desc')
            ->get();

        $eventVenuesByEventId = [];
        $roomsByEventAndVenue = [];

        foreach ($events as $event) {
            $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            if ($venues->isEmpty()) {
                $venues = $event->places->values();
            }

            $eventVenuesByEventId[$event->id] = $venues
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'type' => $p->type])
                ->values()
                ->all();

            $venueIds = $venues->pluck('id');
            if ($venueIds->isEmpty()) {
                continue;
            }

            $children = Place::query()
                ->whereIn('parent_id', $venueIds)
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');

            foreach ($venues as $v) {
                $roomsByEventAndVenue[$event->id][$v->id] = ($children[$v->id] ?? collect())
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                    ->values()
                    ->all();
            }
        }

        return [
            'eventVenuesByEventId' => $eventVenuesByEventId,
            'roomsByEventAndVenue' => $roomsByEventAndVenue,
        ];
    }

    /**
     * Venues linked to an event (prefer `type = venue`, else all).
     *
     * @return Collection<int, Place>
     */
    protected function venuesForEventMassForm(Event $event): Collection
    {
        $event->loadMissing(['places' => fn ($q) => $q->orderBy('name')]);
        $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
        if ($venues->isEmpty()) {
            $venues = $event->places->values();
        }

        return $venues;
    }

    /**
     * @return array<int, list<array{id:int,name:string}>>
     */
    protected function roomOptionsByVenueId(Collection $venues): array
    {
        $out = [];
        $venueIds = $venues->pluck('id');
        if ($venueIds->isEmpty()) {
            return [];
        }

        $children = Place::query()
            ->whereIn('parent_id', $venueIds)
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        foreach ($venues as $v) {
            $out[$v->id] = ($children[$v->id] ?? collect())
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                ->values()
                ->all();
        }

        return $out;
    }

    /**
     * @return array{venue_place_id: int|null, room_name: string|null}
     */
    protected function slotVenueRoomDefaultsFromPlace(Slot $slot): array
    {
        $slot->loadMissing(['place.parent', 'event.places']);
        $place = $slot->place;
        if (! $place) {
            return ['venue_place_id' => null, 'room_name' => null];
        }

        $venues = $this->venuesForEventMassForm($slot->event);
        $venueIds = $venues->pluck('id')->flip();

        if ($venueIds->has($place->id)) {
            return ['venue_place_id' => (int) $place->id, 'room_name' => null];
        }

        if ($place->parent_id && $venueIds->has($place->parent_id)) {
            return ['venue_place_id' => (int) $place->parent_id, 'room_name' => $place->name];
        }

        return ['venue_place_id' => null, 'room_name' => null];
    }

    /**
     * When the event lists candidate venues/places, a slot must pick one (UI defaults the first).
     */
    protected function ensureVenueWhenEventHasPlaces(Request $request, Event $event): void
    {
        if ($this->venuesForEventMassForm($event)->isEmpty()) {
            return;
        }

        $raw = $request->input('venue_place_id');
        if ($raw === null || $raw === '') {
            throw ValidationException::withMessages([
                'venue_place_id' => [__('ui.slots.venue_required_when_event_has_places')],
            ]);
        }
    }

    /**
     * Resolve venue + optional room into a single `places.id` for slot placement.
     */
    protected function resolveMassSlotPlaceId(Request $request, Event $event): ?int
    {
        $venueRaw = $request->input('venue_place_id');
        if ($venueRaw === null || $venueRaw === '') {
            return null;
        }

        $venueId = (int) $venueRaw;

        $eventPlaceIds = $event->places()->pluck('places.id');
        if (! $eventPlaceIds->contains($venueId)) {
            throw ValidationException::withMessages([
                'venue_place_id' => [__('ui.slots.venue_not_linked_to_event')],
            ]);
        }

        $venue = Place::query()->findOrFail($venueId);

        $newRoomName = trim((string) $request->input('new_room_name', ''));
        if ($newRoomName !== '') {
            $existing = Place::query()
                ->where('parent_id', $venueId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($newRoomName)])
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }

            $room = Place::create([
                'name' => $newRoomName,
                'type' => 'room',
                'parent_id' => $venueId,
                'city_id' => $venue->city_id,
                'country_id' => $venue->country_id,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'is_online' => (bool) $venue->is_online,
                'address' => $venue->address,
            ]);

            return (int) $room->id;
        }

        return $venueId;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->boolean('mass')) {
            return redirect()->route('slots.create');
        }

        return $this->storeMass($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(Slot $slot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        $slot->load(['tags', 'event.places', 'place.parent', 'activityTypes']);

        $events = Event::orderBy('starts_at', 'desc')->get();

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();

        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
        $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

        $massPlaceData = $this->slotMassFormPlaceDataForAllEvents();

        $slotVenueRoomDefaults = $this->slotVenueRoomDefaultsFromPlace($slot);

        $lockedEvent = $request->boolean('modal') ? $slot->event : null;

        $slotMassVenues = $lockedEvent ? $this->venuesForEventMassForm($slot->event) : collect();
        $slotMassRoomsByVenueId = $lockedEvent ? $this->roomOptionsByVenueId($slotMassVenues) : [];

        $payload = array_merge(
            compact(
                'slot',
                'events',
                'tags',
                'slotNameSuggestions',
                'slotBaseNameSuggestions',
                'slotMassVenues',
                'slotMassRoomsByVenueId',
                'slotVenueRoomDefaults',
                'lockedEvent'
            ),
            $massPlaceData
        );

        if ($request->boolean('modal')) {
            return view('slots.edit-modal', $payload);
        }

        return view('slots.edit', $payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        try {
            $validated = $request->validate([
                'event_id' => ['required', 'exists:events,id'],
                'name' => ['required', 'string', 'max:255'],
                'starts_at' => ['nullable', 'date'],
                'ends_at' => ['nullable', 'date'],
                'venue_place_id' => ['nullable', 'integer', 'exists:places,id'],
                'new_room_name' => ['nullable', 'string', 'max:255'],
                'requires_approval' => ['nullable', 'boolean'],
                'max_capacity' => ['nullable', 'integer', 'min:1'],
                'activity_types' => ['nullable', 'array'],
                'activity_types.*' => [Rule::in(ActivityController::ACTIVITY_TYPES)],
                'tag_ids' => ['nullable', 'array'],
                'tag_ids.*' => ['integer', 'exists:tags,id'],
                'new_tags' => ['nullable', 'array'],
                'new_tags.*.label' => ['nullable', 'string', 'max:255'],
                'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
            ]);
        } catch (ValidationException $e) {
            return $this->redirectSlotUpdateValidationFailed($request, $slot, $e->errors());
        }

        if (! empty($validated['ends_at']) && empty($validated['starts_at'])) {
            return $this->redirectSlotUpdateValidationFailed($request, $slot, [
                'ends_at' => [__('ui.slots.end_requires_start')],
            ]);
        }

        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])) {
            $startUtc = parse_datetime_to_utc($validated['starts_at']);
            $endUtc = parse_datetime_to_utc($validated['ends_at']);
            if ($startUtc && $endUtc && $endUtc->lt($startUtc)) {
                return $this->redirectSlotUpdateValidationFailed($request, $slot, [
                    'ends_at' => [__('ui.slots.ends_after_start')],
                ]);
            }
        }

        $activityTypes = array_values(array_unique(array_filter(
            $validated['activity_types'] ?? [],
            fn ($t) => in_array($t, ActivityController::ACTIVITY_TYPES, true)
        )));

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            (array) $request->input('tag_ids', []),
            (array) $request->input('new_tags', [])
        );

        $event = Event::query()->findOrFail((int) $validated['event_id']);

        try {
            $this->ensureVenueWhenEventHasPlaces($request, $event);
            $resolvedPlaceId = $this->resolveMassSlotPlaceId($request, $event);
        } catch (ValidationException $e) {
            return $this->redirectSlotUpdateValidationFailed($request, $slot, $e->errors());
        }

        $data = Arr::except($validated, ['activity_types', 'venue_place_id', 'new_room_name']);
        $data['requires_approval'] = $request->boolean('requires_approval');
        if (! empty($data['starts_at'])) {
            $data['starts_at'] = parse_datetime_to_utc($data['starts_at'])?->toDateTimeString();
        } else {
            $data['starts_at'] = null;
        }
        if (! empty($data['ends_at'])) {
            $data['ends_at'] = parse_datetime_to_utc($data['ends_at'])?->toDateTimeString();
        } else {
            $data['ends_at'] = null;
        }

        $slot->update($data);
        $slot->places()->sync($resolvedPlaceId !== null ? [$resolvedPlaceId] : []);
        if (! empty($activityTypes)) {
            $slot->setActivityTypes($activityTypes);
        } else {
            $slot->setActivityTypes([]);
        }
        $slot->tags()->sync($tagIds);

        return $this->redirectAfterSlotUpdate($request, $slot);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    protected function redirectSlotUpdateValidationFailed(Request $request, Slot $slot, array $errors): RedirectResponse
    {
        if ($request->filled('redirect_to_event_slug')) {
            $event = Event::where('slug', $request->string('redirect_to_event_slug'))->first();
            if ($event && (int) $event->id === (int) $slot->event_id) {
                return redirect()->route('events.show', $event)
                    ->withInput()
                    ->withErrors($errors)
                    ->with('open_slot_edit', $slot->id);
            }
        }

        return redirect()->route('slots.index')
            ->withInput()
            ->withErrors($errors)
            ->with('open_slot_edit', $slot->id);
    }

    protected function redirectAfterSlotUpdate(Request $request, Slot $slot): RedirectResponse
    {
        if ($request->filled('redirect_to_event_slug')) {
            $slug = $request->string('redirect_to_event_slug');
            $event = Event::where('slug', $slug)->first();
            if ($event && (int) $event->id === (int) $slot->event_id) {
                return redirect()->route('events.show', $event)
                    ->with('status', __('Slot updated.'));
            }
        }

        return redirect()->route('slots.index')
            ->with('status', __('Slot updated.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Slot $slot)
    {
        $this->authorizeCreatedBy($slot);

        $slot->delete();

        return redirect()->route('slots.index')
            ->with('status', __('Slot deleted.'));
    }

    /**
     * Mass create slots for an event.
     */
    protected function storeMass(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'base_name' => ['required', 'string', 'max:255'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'venue_place_id' => ['nullable', 'integer', 'exists:places,id'],
            'new_room_name' => ['nullable', 'string', 'max:255'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => [Rule::in(ActivityController::ACTIVITY_TYPES)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category' => ['nullable', Rule::in(TagSelectionService::CATEGORY_OPTIONS)],
        ]);

        if (! empty($validated['ends_at']) && empty($validated['starts_at'])) {
            return back()->withErrors(['ends_at' => __('ui.slots.end_requires_start')])->withInput();
        }

        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])) {
            $startUtc = parse_datetime_to_utc($validated['starts_at']);
            $endUtc = parse_datetime_to_utc($validated['ends_at']);
            if ($startUtc && $endUtc && $endUtc->lt($startUtc)) {
                return back()->withErrors(['ends_at' => __('ui.slots.ends_after_start')])->withInput();
            }
        }

        $activityTypes = array_values(array_unique(array_filter(
            $validated['activity_types'] ?? [],
            fn ($t) => in_array($t, ActivityController::ACTIVITY_TYPES, true)
        )));

        $tagIds = $this->tagSelectionService->resolveFinalTagIds(
            (array) $request->input('tag_ids', []),
            (array) $request->input('new_tags', [])
        );

        $event = Event::query()->findOrFail((int) $validated['event_id']);

        try {
            $this->ensureVenueWhenEventHasPlaces($request, $event);
            $resolvedPlaceId = $this->resolveMassSlotPlaceId($request, $event);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $requiresApproval = $request->boolean('requires_approval');
        $startsAtUtc = ! empty($validated['starts_at'])
            ? parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString()
            : null;
        $endsAtUtc = ! empty($validated['ends_at'])
            ? parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString()
            : null;

        for ($i = 1; $i <= $validated['count']; $i++) {
            $slot = Slot::create([
                'event_id' => $validated['event_id'],
                'name' => sprintf('%s #%02d', $validated['base_name'], $i),
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'requires_approval' => $requiresApproval,
                'max_capacity' => $validated['max_capacity'] ?? null,
            ]);

            if ($resolvedPlaceId !== null) {
                $slot->places()->attach($resolvedPlaceId);
            }

            if (! empty($activityTypes)) {
                $slot->setActivityTypes($activityTypes);
            }

            if (! empty($tagIds)) {
                $slot->tags()->sync($tagIds);
            }
        }

        return $this->redirectAfterSlotStore($request, (int) $validated['event_id']);
    }

    /**
     * After creating slot(s), return to the event page when the form was opened with a locked event.
     */
    protected function redirectAfterSlotStore(Request $request, int $eventId): RedirectResponse
    {
        $message = $request->boolean('mass') ? __('Slots created.') : __('Slot created.');

        if ($request->filled('redirect_to_event_slug')) {
            $slug = $request->string('redirect_to_event_slug');
            $event = Event::where('slug', $slug)->first();
            if ($event && $event->id === $eventId) {
                return redirect()->route('events.show', $event)
                    ->with('status', $message);
            }
        }

        return redirect()->route('slots.index')
            ->with('status', $message);
    }
}
