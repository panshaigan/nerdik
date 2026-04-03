<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Collection;

class EventController extends Controller
{
    use AuthorizesOwnership;

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
        return view('events.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $event->load([
            'creator',
            'tags.translations',
            'organization',
            'places',
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity.tags.translations',
                'activityTypes',
                'tags.translations',
            ])->orderBy('starts_at'),
        ]);

        $slotListActivityTagCategories = ['game', 'world', 'convention', 'engine', 'block'];
        $slotHourGroups = $this->slotHourGroupsForEvent($event);
        $places = Place::orderBy('name')->get();
        $pendingProposals = $event->proposals()
            ->with(['activity', 'creator', 'proposedSlots'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
        $isOwner = auth()->check() && $event->created_by === auth()->id();

        $slotFormTags = null;
        $slotNameSuggestions = [];
        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        $slotBaseNameSuggestions = [];
        if ($isOwner) {
            $slotFormTags = Tag::with(['translations', 'aliases', 'attachedTags'])->orderBy('category')->orderBy('slug')->get();
            $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
            $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

            $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            if ($venues->isEmpty()) {
                $venues = $event->places->values();
            }
            $slotMassVenues = $venues;
            $venueIds = $venues->pluck('id');
            if ($venueIds->isNotEmpty()) {
                $children = Place::query()
                    ->whereIn('parent_id', $venueIds)
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
                foreach ($venues as $v) {
                    $slotMassRoomsByVenueId[$v->id] = ($children[$v->id] ?? collect())
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                        ->values()
                        ->all();
                }
            }
        }

        return view('events.show', compact(
            'event',
            'places',
            'pendingProposals',
            'isOwner',
            'slotFormTags',
            'slotNameSuggestions',
            'slotMassVenues',
            'slotMassRoomsByVenueId',
            'slotBaseNameSuggestions',
            'slotHourGroups',
            'slotListActivityTagCategories'
        ));
    }

    /**
     * Group slots by calendar hour (user timezone) for the event schedule list.
     *
     * @return list<array{label: string, slots: Collection<int, Slot>}>
     */
    protected function slotHourGroupsForEvent(Event $event): array
    {
        $sorted = $event->slots
            ->sortBy(fn (Slot $s) => $s->starts_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $grouped = $sorted->groupBy(function (Slot $slot) {
            if (! $slot->starts_at) {
                return '__no_time__';
            }

            return format_in_user_tz($slot->starts_at, 'Y-m-d H');
        })->sortKeys();

        $out = [];
        foreach ($grouped as $key => $groupSlots) {
            $out[] = [
                'label' => $key === '__no_time__'
                    ? __('ui.events.slots_group_no_time')
                    : format_in_user_tz($groupSlots->first()->starts_at, 'D, M j · H:00'),
                'slots' => $groupSlots,
            ];
        }

        return $out;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorizeCreatedBy($event);

        return view('events.edit', compact('event'));
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

        foreach ($event->slots()->with('place')->get() as $slot) {
            $newSlot = $newEvent->slots()->create([
                'name' => $slot->name,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'requires_approval' => $slot->requires_approval,
                'max_capacity' => $slot->max_capacity,
                'activity_id' => null, // important: new event has empty slots
            ]);
            if ($slot->place) {
                $newSlot->places()->sync([$slot->place->id]);
            }
        }

        return redirect()->route('events.show', $newEvent)
            ->with('status', __('Event copied.'));
    }
}
