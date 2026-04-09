<?php

namespace App\Livewire\Events;

use App\Enums\ActivityProposalStatus;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\TagCategory;
use App\Services\ActivityHostingModeService;
use App\Services\ActivityProposalDecisionService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

    /**
     * Pending proposal accept: slot id per proposal (empty string = auto-assign). Keys are proposal ids.
     *
     * @var array<int|string, int|string>
     */
    public array $proposalAcceptSlotId = [];

    /** @var array<int|string, string> */
    public array $slotCancelReason = [];

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

    public function detachActivityFromSlot(int $slotId, ActivityProposalDecisionService $decisions): void
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

        $decisions->detachActivityFromSlot($event, $slot);

        $this->refreshAfterSlotMutation();
        session()->flash('status', __('ui.status.activity_detached_from_slot'));
    }

    public function cancelSlotActivity(int $slotId, ActivityHostingModeService $hostingModes): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()
            ->with('activity')
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $activity = $slot->activity;
        if ($activity === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user?->canModifyEntity($event) || $user?->canModifyEntity($activity), 403);

        $reason = $this->slotCancelReason[$slotId]
            ?? $this->slotCancelReason[(string) $slotId]
            ?? null;
        $reason = is_string($reason) ? trim($reason) : null;
        if ($reason === '') {
            $reason = null;
        }

        if ($reason !== null && mb_strlen($reason) > 1000) {
            $this->addError('slotCancelReason.'.$slotId, __('validation.max.string', [
                'attribute' => 'cancel_reason',
                'max' => 1000,
            ]));

            return;
        }

        $hostingModes->cancel($activity, $user, $reason);
        unset($this->slotCancelReason[$slotId], $this->slotCancelReason[(string) $slotId]);
        $this->slotListVersion++;
        session()->flash('status', __('ui.activities.cancelled_status'));
    }

    public function reopenSlotActivity(int $slotId, ActivityHostingModeService $hostingModes): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $slot = Slot::query()
            ->with('activity')
            ->whereKey($slotId)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $activity = $slot->activity;
        if ($activity === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user?->canModifyEntity($event) || $user?->canModifyEntity($activity), 403);

        $hostingModes->reopen($activity);
        $this->slotListVersion++;
        session()->flash('status', __('ui.activities.reopened_status'));
    }

    public function acceptPendingProposal(int $proposalId, ActivityProposalDecisionService $decisions): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($event), 403);

        $proposal = ActivityProposal::query()
            ->whereKey($proposalId)
            ->where('event_id', $this->eventId)
            ->firstOrFail();

        if ($proposal->status !== ActivityProposalStatus::Pending) {
            session()->flash('status', __('ui.status.proposal_not_pending'));

            return;
        }

        $rawSlotId = $this->proposalAcceptSlotId[$proposalId]
            ?? $this->proposalAcceptSlotId[(string) $proposalId]
            ?? '';

        try {
            $decisions->accept($proposal, $rawSlotId);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('proposalAcceptSlot.'.$proposalId, $message);
                }
            }

            return;
        }

        unset($this->proposalAcceptSlotId[$proposalId], $this->proposalAcceptSlotId[(string) $proposalId]);
        $this->slotListVersion++;
        session()->flash('status', __('ui.status.proposal_accepted'));
    }

    public function rejectPendingProposal(int $proposalId, ActivityProposalDecisionService $decisions): void
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        abort_unless(auth()->user()?->canModifyEntity($event), 403);

        $proposal = ActivityProposal::query()
            ->whereKey($proposalId)
            ->where('event_id', $this->eventId)
            ->firstOrFail();

        if ($proposal->status !== ActivityProposalStatus::Pending) {
            session()->flash('status', __('ui.status.proposal_not_pending'));

            return;
        }

        $decisions->reject($proposal);
        $this->slotListVersion++;
        session()->flash('status', __('ui.status.proposal_rejected'));
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
                    : format_datetime_in_user_tz($groupSlots->first()->starts_at, 'ddd, D MMM · HH:00'),
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
                'label' => format_datetime_in_user_tz($event->starts_at, 'ddd, D MMM · HH:00'),
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
                'label' => format_datetime_in_user_tz($event->ends_at, 'ddd, D MMM · HH:00'),
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
            'organization',
            'places',
            'enrollmentWindows',
            'slots' => fn ($q) => $q->with([
                'place.parent',
                'activity.tags.translations',
                'activity.tags.tagCategory',
                'activity.activityType',
                'activity.canceller',
                'activityTypes',
                'activityTypes.activityType',
            ])->orderBy('starts_at'),
        ]);

        $this->syncSlotEndsFromActivityDuration($event);

        $now = now();
        $activeEnrollmentWindow = $event->enrollmentWindows->first(function ($w) use ($now) {
            return $w->starts_at !== null
                && $w->ends_at !== null
                && $now->between($w->starts_at, $w->ends_at);
        });

        $slotListActivityTagCategories = TagCategory::ACTIVITY_HIGHLIGHT_KEYS;
        $slotHourGroups = $this->slotHourGroupsForEvent($event);
        $pendingProposals = $event->proposals()
            ->with(['activity.tags.translations', 'activity.tags.tagCategory', 'activity.activityType', 'creator', 'proposedSlots'])
            ->where('status', ActivityProposalStatus::Pending)
            ->orderBy('created_at')
            ->get();
        $user = auth()->user();
        $canManageEvent = $user !== null && $user->canModifyEntity($event);

        $slotNameSuggestions = [];
        $slotMassVenues = collect();
        $slotMassRoomsByVenueId = [];
        $slotBaseNameSuggestions = [];
        if ($canManageEvent) {
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
            'slotNameSuggestions' => $slotNameSuggestions,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'slotBaseNameSuggestions' => $slotBaseNameSuggestions,
            'slotHourGroups' => $slotHourGroups,
            'slotListActivityTagCategories' => $slotListActivityTagCategories,
        ]);
    }
}
