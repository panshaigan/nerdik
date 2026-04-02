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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SlotController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly TagSelectionService $tagSelectionService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $slots = Slot::with(['event', 'place'])
            ->orderBy('starts_at')
            ->get();

        return view('slots.index', compact('slots'));
    }

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

        $places = Place::orderBy('name')->get();

        $slot = new Slot;
        if ($lockedEvent) {
            $slot->event_id = $lockedEvent->id;
        }

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());

        return view('slots.create', [
            'slot' => $slot,
            'events' => $events,
            'places' => $places,
            'lockedEvent' => $lockedEvent,
            'tags' => $tags,
            'slotNameSuggestions' => $slotNameSuggestions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->boolean('mass')) {
            return $this->storeMass($request);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'place_id' => ['nullable', 'exists:places,id'],
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
            throw ValidationException::withMessages(['ends_at' => [__('ui.slots.end_requires_start')]]);
        }

        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])) {
            $startUtc = parse_datetime_to_utc($validated['starts_at']);
            $endUtc = parse_datetime_to_utc($validated['ends_at']);
            if ($startUtc && $endUtc && $endUtc->lt($startUtc)) {
                throw ValidationException::withMessages(['ends_at' => [__('ui.slots.ends_after_start')]]);
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

        $data = Arr::except($validated, ['activity_types']);
        $data['activity_types'] = ! empty($activityTypes) ? $activityTypes : null;
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

        $slot = Slot::create($data);
        if (! empty($tagIds)) {
            $slot->tags()->sync($tagIds);
        }

        return $this->redirectAfterSlotStore($request, (int) $validated['event_id']);
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

        $slot->load(['tags', 'event']);

        $events = Event::orderBy('starts_at', 'desc')->get();

        $places = Place::orderBy('name')->get();

        $tags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();

        $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());

        $lockedEvent = $request->boolean('modal') ? $slot->event : null;

        $payload = compact('slot', 'events', 'places', 'tags', 'slotNameSuggestions', 'lockedEvent');

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
                'place_id' => ['nullable', 'exists:places,id'],
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

        $data = Arr::except($validated, ['activity_types']);
        $data['activity_types'] = ! empty($activityTypes) ? $activityTypes : null;
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
            'place_id' => ['nullable', 'exists:places,id'],
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
                'activity_types' => ! empty($activityTypes) ? $activityTypes : null,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'place_id' => $validated['place_id'] ?? null,
                'requires_approval' => $requiresApproval,
                'max_capacity' => $validated['max_capacity'] ?? null,
            ]);

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
