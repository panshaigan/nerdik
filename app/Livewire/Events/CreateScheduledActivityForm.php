<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\ParticipationMode;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\OrganizerScheduledActivityService;
use App\Services\SlotFormService;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class CreateScheduledActivityForm extends Component
{
    use AuthorizesOwnership;
    use Toast;

    #[Locked]
    public int $eventId;

    public string $name = '';

    public ?int $activity_type_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public ?int $venue_place_id = null;

    public string $new_room_name = '';

    public string $participation_mode = 'open';

    public ?int $lottery_draw_in_hours = 24;

    public bool $allows_observers = false;

    public bool $collect_familiarity = false;

    public bool $participationConstrainedBySlots = false;

    /** @var list<string> */
    public array $allowedParticipationModes = [];

    /** @var list<int> */
    public array $allowedLotteryDrawHours = [];

    /** @var list<bool> */
    public array $allowedAllowsObservers = [];

    public bool $lotteryDrawHoursLockedBySlots = false;

    public bool $allowsObserversLockedBySlots = false;

    public bool $modalOpen = false;

    public int $formBootKey = 0;

    public function hydrate(): void
    {
        Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->firstOrFail();
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->resetActivityTypeDefault();
    }

    #[On('open-schedule-activity')]
    public function handleOpen(int $eventId = 0): void
    {
        if ($eventId !== 0 && $eventId !== $this->eventId) {
            return;
        }

        $this->openModal();
    }

    public function openModal(): void
    {
        $event = $this->resolveEventForOrganizer();
        abort_if($event->isCancelled(), 403);

        $this->modalOpen = true;
        $this->resetForm();
        $this->applyVenueDefaults($event);
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function updatedVenuePlaceId(): void
    {
        $this->new_room_name = '';
    }

    public function save(OrganizerScheduledActivityService $scheduledActivities): void
    {
        $event = $this->resolveEventForOrganizer();
        abort_if($event->isCancelled(), 403);

        $eventRequiresVenue = app(SlotFormService::class)->venuesForEventMassForm($event)->isNotEmpty();

        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
                'starts_at' => ['required', 'date'],
                'ends_at' => [
                    'required',
                    'date',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        $startUtc = parse_datetime_to_utc($this->starts_at);
                        $endUtc = parse_datetime_to_utc((string) $value);
                        if ($startUtc !== null && $endUtc !== null && $endUtc->lt($startUtc)) {
                            $fail(__('ui.slots.ends_after_start'));
                        }
                    },
                ],
                'venue_place_id' => [
                    Rule::requiredIf($eventRequiresVenue),
                    'nullable',
                    'integer',
                    'exists:places,id',
                ],
                'new_room_name' => ['nullable', 'string', 'max:255'],
                'participation_mode' => ['required', 'string', Rule::in(ParticipationMode::values())],
                'lottery_draw_in_hours' => [
                    Rule::requiredIf(fn (): bool => $this->participation_mode === ParticipationMode::Lottery->value),
                    'nullable',
                    'integer',
                    'min:1',
                    'max:48',
                ],
                'allows_observers' => ['nullable', 'boolean'],
                'collect_familiarity' => ['nullable', 'boolean'],
            ],
            [],
            [
                'name' => __('ui.activities.name'),
                'activity_type_id' => __('ui.activities.type'),
                'starts_at' => __('ui.slots.starts_at_optional'),
                'ends_at' => __('ui.slots.ends_at_optional'),
                'venue_place_id' => __('ui.slots.venue_optional'),
            ],
        );

        $scheduledActivities->create($event, $this->actingUser(), $validated);

        $this->success(__('ui.events.schedule_activity_success'));
        $this->dispatch('slot-mutations-refresh');
        $this->resetForm();
        $this->modalOpen = false;
    }

    public function render(SlotFormService $slotForms): View
    {
        $event = Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->with('places')->firstOrFail();
        $activityTypes = ActivityType::query()->orderBy('id')->get(['id', 'slug']);
        $slotMassVenues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
        $slotMassRoomsByVenueId = [];
        if ($slotMassVenues->isNotEmpty()) {
            $children = Place::query()
                ->whereIn('parent_id', $slotMassVenues->pluck('id'))
                ->where('type', 'room')
                ->orderBy('name')
                ->get()
                ->groupBy('parent_id');
            foreach ($slotMassVenues as $venue) {
                $slotMassRoomsByVenueId[$venue->id] = ($children[$venue->id] ?? collect())
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
                    ->values()
                    ->all();
            }
        }

        $datetimeMinuteStepSeconds = max(1, (int) config('ui-datetime.minute_step', 5)) * 60;

        return view('livewire.events.create-scheduled-activity-form', [
            'event' => $event,
            'activityTypes' => $activityTypes,
            'slotMassVenues' => $slotMassVenues,
            'slotMassRoomsByVenueId' => $slotMassRoomsByVenueId,
            'singleVenueLocked' => $slotMassVenues->count() === 1,
            'eventDatetimeBounds' => [
                'starts_at' => $event->starts_at ? format_datetime_local_input($event->starts_at) : null,
                'ends_at' => $event->ends_at ? format_datetime_local_input($event->ends_at) : null,
            ],
            'datetimeMinuteStepSeconds' => $datetimeMinuteStepSeconds,
        ]);
    }

    private function resolveEventForOrganizer(): Event
    {
        $event = Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->with('places')->firstOrFail();
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canModifyEntity($event), 403);

        return $event;
    }

    private function actingUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->venue_place_id = null;
        $this->new_room_name = '';
        $this->participation_mode = ParticipationMode::Open->value;
        $this->lottery_draw_in_hours = 24;
        $this->allows_observers = false;
        $this->collect_familiarity = false;
        $this->resetActivityTypeDefault();
        $this->resetValidation();
    }

    private function resetActivityTypeDefault(): void
    {
        $rpgId = ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $this->activity_type_id = $rpgId !== null ? (int) $rpgId : null;
    }

    private function applyVenueDefaults(Event $event): void
    {
        $venues = $event->places->filter(fn ($p) => $p->type === 'venue')->values();
        if ($venues->count() === 1) {
            $this->venue_place_id = (int) $venues->first()->id;
        }
    }
}
