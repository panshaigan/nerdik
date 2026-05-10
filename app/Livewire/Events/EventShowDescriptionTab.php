<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\EventSlotPresentationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;
use Livewire\Component;

/**
 * Description panel for {@see ShowEvent}. Mounted only when the shell `tab` is `description` ({@see ShowEvent::$tab}).
 * Tab selection and `?tab=` live on the parent; do not bind `tab` to the query string here.
 */
#[Defer]
class EventShowDescriptionTab extends Component
{
    public int $eventId;

    /**
     * Mirrors {@see ShowEvent::$tab} from the shell; for debugging/contracts — not read from the request URL.
     */
    public string $activeTab = 'description';

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
