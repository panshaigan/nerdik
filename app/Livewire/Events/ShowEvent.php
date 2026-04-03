<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Livewire\Component;

class ShowEvent extends Component
{
    public int $eventId;

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
    }

    /**
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

    public function render()
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

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

        return view('livewire.events.show-event', [
            'event' => $event,
            'pendingProposals' => $pendingProposals,
            'isOwner' => $isOwner,
            'slotFormTags' => $slotFormTags,
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotHourGroups' => $slotHourGroups,
            'slotListActivityTagCategories' => $slotListActivityTagCategories,
        ]);
    }
}
