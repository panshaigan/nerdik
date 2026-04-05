<?php

namespace App\Livewire\Events;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowEvent extends Component
{
    use AuthorizesOwnership;

    public int $eventId;

    /** Bumped when slots change via async JS so the component re-renders. */
    public int $slotListVersion = 0;

    /** Free slots marked for “Propose an activity” preferred slots (slot ids). */
    public array $proposalSlotIds = [];

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
    }

    public function toggleProposalSlot(int $slotId): void
    {
        $slot = Slot::query()
            ->whereKey($slotId)
            ->where('event_id', $this->eventId)
            ->whereNull('activity_id')
            ->first();
        if ($slot === null) {
            return;
        }

        $ids = array_map('intval', $this->proposalSlotIds);
        $key = array_search($slotId, $ids, true);
        if ($key !== false) {
            unset($ids[$key]);
            $this->proposalSlotIds = array_values($ids);
        } else {
            $this->proposalSlotIds = [...$ids, $slotId];
        }
    }

    #[On('slot-mutations-refresh')]
    public function refreshAfterSlotMutation(): void
    {
        $this->slotListVersion++;
        $event = Event::query()->whereKey($this->eventId)->first();
        if ($event !== null) {
            $event->loadMissing('slots');
            $freeIds = $event->slots->whereNull('activity_id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->proposalSlotIds = array_values(array_intersect(
                array_map('intval', $this->proposalSlotIds),
                $freeIds
            ));
        }
    }

    public function deleteEvent(): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);
        $event->delete();
        session()->flash('status', __('Event deleted.'));
        $this->redirect(route('search.index'), navigate: true);
    }

    public function deleteSlot(int $slotId): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()->whereKey($slotId)->firstOrFail();

        if ((int) $slot->event_id !== (int) $event->id) {
            abort(404);
        }

        $this->authorizeCreatedBy($slot);
        $slot->delete();
        $this->proposalSlotIds = array_values(array_filter(
            $this->proposalSlotIds,
            fn ($id) => (int) $id !== (int) $slotId
        ));
    }

    public function detachActivityFromSlot(int $slotId): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $this->authorizeCreatedBy($event);

        $slot = Slot::query()
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        if ($slot->activity_id === null) {
            return;
        }

        DB::transaction(function () use ($slot, $event) {
            $activityId = $slot->activity_id;

            $proposal = ActivityProposal::query()
                ->where('event_id', $event->id)
                ->where('activity_id', $activityId)
                ->where('accepted_slot_id', $slot->id)
                ->first();

            if ($proposal === null) {
                $proposal = ActivityProposal::query()
                    ->where('event_id', $event->id)
                    ->where('activity_id', $activityId)
                    ->where('status', ActivityProposalStatus::Accepted)
                    ->first();
            }

            $slot->update(['activity_id' => null]);

            if ($proposal !== null) {
                $proposal->update([
                    'status' => ActivityProposalStatus::Pending,
                    'accepted_slot_id' => null,
                ]);
            }
        });

        $this->refreshAfterSlotMutation();
        session()->flash('status', __('ui.status.activity_detached_from_slot'));
    }

    /**
     * @return list<array{label: string, slots: Collection<int, Slot>, boundary?: string}>
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

        $firstTimedSlot = $sorted->first(fn (Slot $s) => $s->starts_at !== null);
        $prependEventStart = false;
        if ($event->starts_at) {
            if ($firstTimedSlot === null) {
                $prependEventStart = true;
            } elseif ($event->starts_at->lt($firstTimedSlot->starts_at)) {
                $eventHour = format_in_user_tz($event->starts_at, 'Y-m-d H');
                $firstHour = format_in_user_tz($firstTimedSlot->starts_at, 'Y-m-d H');
                $prependEventStart = $eventHour !== $firstHour;
            }
        }

        if ($prependEventStart) {
            array_unshift($out, [
                'label' => format_in_user_tz($event->starts_at, 'D, M j · H:00'),
                'slots' => collect(),
                'boundary' => 'event_start',
            ]);
        }

        $lastSlot = $sorted->last();
        $lastSlotEnd = $lastSlot?->ends_at ?? $lastSlot?->starts_at;
        $appendEventEnd = $event->ends_at !== null
            && ($lastSlotEnd === null || ! $event->ends_at->equalTo($lastSlotEnd));

        if ($appendEventEnd) {
            $out[] = [
                'label' => format_in_user_tz($event->ends_at, 'D, M j · H:00'),
                'slots' => collect(),
                'boundary' => 'event_end',
            ];
        }

        return $out;
    }

    /**
     * When a slot has an activity with a duration, ensure ends_at matches start + duration.
     */
    protected function syncSlotEndsFromActivityDuration(Event $event): void
    {
        foreach ($event->slots as $slot) {
            $activity = $slot->activity;
            if ($activity === null || ! $slot->starts_at) {
                continue;
            }
            $minutes = (int) ($activity->duration_in_minutes ?? 0);
            if ($minutes <= 0) {
                continue;
            }
            $expected = $slot->starts_at->copy()->addMinutes($minutes);
            if ($slot->ends_at === null || ! $slot->ends_at->equalTo($expected)) {
                $slot->ends_at = $expected;
                $slot->save();
            }
        }
    }

    public function render()
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();

        $event->load([
            'creator',
            'tags.translations',
            'organization',
            'places',
            'enrollmentWindows',
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity.tags.translations',
                'activityTypes',
                'tags.translations',
            ])->orderBy('starts_at'),
        ]);

        $this->syncSlotEndsFromActivityDuration($event);

        $now = now();
        $activeEnrollmentWindow = $event->enrollmentWindows->first(function ($w) use ($now) {
            return $w->starts_at !== null
                && $w->ends_at !== null
                && $now->between($w->starts_at, $w->ends_at);
        });

        $slotListActivityTagCategories = ['game', 'world', 'convention', 'engine', 'block'];
        $slotHourGroups = $this->slotHourGroupsForEvent($event);
        $pendingProposals = $event->proposals()
            ->with(['activity.tags.translations', 'creator', 'proposedSlots'])
            ->where('status', ActivityProposalStatus::Pending)
            ->orderBy('created_at')
            ->get();
        $user = auth()->user();
        $canManageEvent = $user !== null && $user->canModifyEntity($event);

        $slotFormTags = null;
        $slotNameSuggestions = [];
        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        $slotBaseNameSuggestions = [];
        if ($canManageEvent) {
            $slotFormTags = Tag::with(['translations', 'aliases', 'tagAttachments'])->orderBy('category')->orderBy('slug')->get();
            $slotNameSuggestions = Slot::distinctNameSuggestionsForUser(auth()->id());
            $slotBaseNameSuggestions = Slot::baseNameSuggestionsForUser(auth()->id());

            $slotMassVenues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
            $venueIds = $slotMassVenues->pluck('id');
            if ($venueIds->isNotEmpty()) {
                $children = Place::query()
                    ->whereIn('parent_id', $venueIds)
                    ->where('type', 'room')
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');
                foreach ($slotMassVenues as $v) {
                    $slotMassRoomsByVenueId[$v->id] = ($children[$v->id] ?? collect())
                        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                        ->values()
                        ->all();
                }
            }
        }

        return view('livewire.events.show-event', [
            'event' => $event,
            'activeEnrollmentWindow' => $activeEnrollmentWindow,
            'pendingProposals' => $pendingProposals,
            'canManageEvent' => $canManageEvent,
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
