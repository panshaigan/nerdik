<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SlotFormService
{
    private const MASS_CREATE_COUNT_MIN = 1;

    private const MASS_CREATE_COUNT_MAX = 100;

    private const SLOT_MAX_CAPACITY_MIN = 0;

    /**
     * Venues and room lists for the mass-create form when the event is not locked.
     *
     * @return array{eventVenuesByEventId: array<int, list<array{id:int,name:string,type:string}>>, roomsByEventAndVenue: array<int, array<int, list<array{id:int,name:string}>>>}
     */
    public function massFormPlaceDataForAllEvents(): array
    {
        $events = Event::with(['places' => fn ($q) => $q->orderBy('name')])
            ->orderBy('starts_at', 'desc')
            ->get();

        $eventVenuesByEventId = [];
        $roomsByEventAndVenue = [];

        foreach ($events as $event) {
            $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();

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
                ->where('type', 'room')
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
     * Venues and room lists for the mass-create form scoped to one user's accessible events.
     *
     * @return array{eventVenuesByEventId: array<int, list<array{id:int,name:string,type:string}>>, roomsByEventAndVenue: array<int, array<int, list<array{id:int,name:string}>>>}
     */
    public function massFormPlaceDataForUser(?int $userId, bool $isAdmin = false): array
    {
        $events = Event::query()
            ->with(['places' => fn ($q) => $q->orderBy('name')])
            ->when(! $isAdmin, fn ($query) => $query->where('created_by', $userId))
            ->orderBy('starts_at', 'desc')
            ->get();

        $eventVenuesByEventId = [];
        $roomsByEventAndVenue = [];

        foreach ($events as $event) {
            $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();

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
                ->where('type', 'room')
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
    public function venuesForEventMassForm(Event $event): Collection
    {
        $event->loadMissing(['places' => fn ($q) => $q->orderBy('name')]);

        return $event->places->filter(fn ($p) => $p->type === 'venue')->values();
    }

    /**
     * @return array<int, list<array{id:int,name:string}>>
     */
    public function roomOptionsByVenueId(Collection $venues): array
    {
        $out = [];
        $venueIds = $venues->pluck('id');
        if ($venueIds->isEmpty()) {
            return [];
        }

        $children = Place::query()
            ->whereIn('parent_id', $venueIds)
            ->where('type', 'room')
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
    public function slotVenueRoomDefaultsFromPlace(Slot $slot): array
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
    public function ensureVenueWhenEventHasPlaces(Request $request, Event $event): void
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
    public function resolveMassSlotPlaceId(Request $request, Event $event): ?int
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
     * @return array<string, mixed>
     */
    protected function massCreateValidationRules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'base_name' => ['required', 'string', 'max:255'],
            'count' => ['required', 'integer', 'min:'.self::MASS_CREATE_COUNT_MIN, 'max:'.self::MASS_CREATE_COUNT_MAX],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'venue_place_id' => ['nullable', 'integer', 'exists:places,id'],
            'new_room_name' => ['nullable', 'string', 'max:255'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:'.self::SLOT_MAX_CAPACITY_MIN],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['integer', 'exists:activity_types,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function slotUpdateValidationRules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'venue_place_id' => ['nullable', 'integer', 'exists:places,id'],
            'new_room_name' => ['nullable', 'string', 'max:255'],
            'requires_approval' => ['nullable', 'boolean'],
            'max_capacity' => ['nullable', 'integer', 'min:'.self::SLOT_MAX_CAPACITY_MIN],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['integer', 'exists:activity_types,id'],
        ];
    }

    private function normalizeMaxCapacity(mixed $maxCapacity): ?int
    {
        if ($maxCapacity === null || $maxCapacity === '') {
            return null;
        }

        $normalized = (int) $maxCapacity;

        return $normalized === 0 ? null : $normalized;
    }

    /**
     * Determine the first index for a new mass-created name batch in a strict "<base> #NN" format.
     */
    private function nextMassSlotIndexSeed(int $eventId, ?string $startsAtUtc, ?int $placeId, string $baseName): int
    {
        $normalizedBaseName = trim($baseName);
        if ($normalizedBaseName === '') {
            return 1;
        }

        $query = Slot::query()
            ->where('event_id', $eventId)
            ->whereNull('deleted_at');

        if ($startsAtUtc === null) {
            $query->whereNull('starts_at');
        } else {
            $query->where('starts_at', $startsAtUtc);
        }

        if ($placeId === null) {
            $query->whereNull('place_id');
        } else {
            $query->where('place_id', $placeId);
        }

        $candidateNames = $query
            ->where('name', 'like', $normalizedBaseName.' #%')
            ->pluck('name');

        $strictPattern = '/^'.preg_quote($normalizedBaseName, '/').'\s#(\d{2,})$/u';
        $maxExistingIndex = 0;

        foreach ($candidateNames as $candidateName) {
            if (! is_string($candidateName)) {
                continue;
            }

            if (preg_match($strictPattern, $candidateName, $matches) !== 1) {
                continue;
            }

            $maxExistingIndex = max($maxExistingIndex, (int) $matches[1]);
        }

        return $maxExistingIndex + 1;
    }

    /**
     * Persist mass-created slots. Throws {@see ValidationException} on validation / business-rule failure.
     */
    public function performMassCreate(Request $request): void
    {
        $validated = $request->validate($this->massCreateValidationRules());

        if (! empty($validated['ends_at']) && empty($validated['starts_at'])) {
            throw ValidationException::withMessages([
                'ends_at' => [__('ui.slots.end_requires_start')],
            ]);
        }

        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])) {
            $startUtc = parse_datetime_to_utc($validated['starts_at']);
            $endUtc = parse_datetime_to_utc($validated['ends_at']);
            if ($startUtc && $endUtc && $endUtc->lt($startUtc)) {
                throw ValidationException::withMessages([
                    'ends_at' => [__('ui.slots.ends_after_start')],
                ]);
            }
        }

        $requestedTypeIds = array_values(array_unique(array_map('intval', $validated['activity_types'] ?? [])));
        $activityTypes = ActivityType::query()
            ->whereIn('id', $requestedTypeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $event = Event::query()->findOrFail((int) $validated['event_id']);

        $this->ensureVenueWhenEventHasPlaces($request, $event);
        $resolvedPlaceId = $this->resolveMassSlotPlaceId($request, $event);

        $requiresApproval = $request->boolean('requires_approval');
        $startsAtUtc = ! empty($validated['starts_at'])
            ? parse_datetime_to_utc($validated['starts_at'])?->toDateTimeString()
            : null;
        $endsAtUtc = ! empty($validated['ends_at'])
            ? parse_datetime_to_utc($validated['ends_at'])?->toDateTimeString()
            : null;
        $baseName = trim((string) $validated['base_name']);
        $firstIndex = $this->nextMassSlotIndexSeed(
            (int) $validated['event_id'],
            $startsAtUtc,
            $resolvedPlaceId,
            $baseName
        );

        for ($i = 0; $i < (int) $validated['count']; $i++) {
            $slot = Slot::create([
                'event_id' => $validated['event_id'],
                'name' => sprintf('%s #%02d', $baseName, $firstIndex + $i),
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'requires_approval' => $requiresApproval,
                'place_id' => $resolvedPlaceId,
                'max_capacity' => $this->normalizeMaxCapacity($validated['max_capacity'] ?? null),
            ]);

            if (! empty($activityTypes)) {
                $slot->setActivityTypes($activityTypes);
            }

        }
    }

    /**
     * Update a slot in place. Throws {@see ValidationException} on failure.
     */
    public function performSlotUpdate(Request $request, Slot $slot): void
    {
        $validated = $request->validate($this->slotUpdateValidationRules());

        if (! empty($validated['ends_at']) && empty($validated['starts_at'])) {
            throw ValidationException::withMessages([
                'ends_at' => [__('ui.slots.end_requires_start')],
            ]);
        }

        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])) {
            $startUtc = parse_datetime_to_utc($validated['starts_at']);
            $endUtc = parse_datetime_to_utc($validated['ends_at']);
            if ($startUtc && $endUtc && $endUtc->lt($startUtc)) {
                throw ValidationException::withMessages([
                    'ends_at' => [__('ui.slots.ends_after_start')],
                ]);
            }
        }

        $requestedTypeIds = array_values(array_unique(array_map('intval', $validated['activity_types'] ?? [])));
        $activityTypes = ActivityType::query()
            ->whereIn('id', $requestedTypeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $event = Event::query()->findOrFail((int) $validated['event_id']);

        $this->ensureVenueWhenEventHasPlaces($request, $event);
        $resolvedPlaceId = $this->resolveMassSlotPlaceId($request, $event);

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
        $data['max_capacity'] = $this->normalizeMaxCapacity($data['max_capacity'] ?? null);

        DB::transaction(function () use ($slot, $data, $resolvedPlaceId, $activityTypes): void {
            $data['place_id'] = $resolvedPlaceId;
            $slot->update($data);
            if (! empty($activityTypes)) {
                $slot->setActivityTypes($activityTypes);
            } else {
                $slot->setActivityTypes([]);
            }
            $slot->load(['activity', 'activityTypes']);
            if ($slot->activity !== null && ! $slot->fitsProposalActivity($slot->activity)) {
                throw ValidationException::withMessages([
                    'max_capacity' => [__('ui.slots.slot_update_breaks_assigned_activity')],
                ]);
            }
        });
    }

    public function massCreate(Request $request): RedirectResponse
    {
        try {
            $this->performMassCreate($request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return $this->redirectAfterSlotStore($request, (int) $request->input('event_id'));
    }

    public function updateSlot(Request $request, Slot $slot): RedirectResponse
    {
        try {
            $this->performSlotUpdate($request, $slot);
        } catch (ValidationException $e) {
            return $this->redirectSlotUpdateValidationFailed($request, $slot, $e->errors());
        }

        return $this->redirectAfterSlotUpdate($request, $slot);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function redirectSlotUpdateValidationFailed(Request $request, Slot $slot, array $errors): RedirectResponse
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

        return redirect()->route('dashboard')
            ->withInput()
            ->withErrors($errors)
            ->with('open_slot_edit', $slot->id);
    }

    public function redirectAfterSlotUpdate(Request $request, Slot $slot): RedirectResponse
    {
        if ($request->filled('redirect_to_event_slug')) {
            $slug = $request->string('redirect_to_event_slug');
            $event = Event::where('slug', $slug)->first();
            if ($event && (int) $event->id === (int) $slot->event_id) {
                return redirect()->route('events.show', $event)
                    ->with('status', __('Slot updated.'));
            }
        }

        return redirect()->route('dashboard')
            ->with('status', __('Slot updated.'));
    }

    /**
     * After creating slot(s), return to the event page when the form was opened with a locked event.
     */
    public function redirectAfterSlotStore(Request $request, int $eventId): RedirectResponse
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

        return redirect()->route('dashboard')
            ->with('status', $message);
    }
}
