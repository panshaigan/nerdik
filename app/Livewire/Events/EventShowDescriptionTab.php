<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\EventSlotPresentationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;
use Livewire\Component;

#[Defer]
class EventShowDescriptionTab extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render(EventSlotPresentationService $slotPresentation): View
    {
        $event = Event::query()->whereKey($this->eventId)->firstOrFail();
        $event->load(['places', 'enrollmentWindows']);

        $enrollment = $slotPresentation->enrollmentPresentation($event, now());

        return view('livewire.events.event-show-description-tab', [
            'event' => $event,
            'activeEnrollmentWindow' => $enrollment->activeWindow,
        ]);
    }
}
