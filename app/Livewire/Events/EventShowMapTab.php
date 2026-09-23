<?php

namespace App\Livewire\Events;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Defer;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Map panel for {@see ShowEvent}. Mounted only when the shell `tab` is `map` ({@see ShowEvent::$tab}).
 * Tab selection and `?tab=` live on the parent; do not bind `tab` to the query string here.
 */
#[Defer]
class EventShowMapTab extends Component
{
    #[Locked]
    public int $eventId;

    /**
     * Mirrors {@see ShowEvent::$tab} from the shell; for debugging/contracts — not read from the request URL.
     */
    public string $activeTab = 'map';

    public function hydrate(): void
    {
        Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->firstOrFail();
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render(): View
    {
        $event = Event::query()->visibleTo(auth()->user())->whereKey($this->eventId)->firstOrFail();
        $event->load(['places']);

        return view('livewire.events.event-show-map-tab', [
            'event' => $event,
        ]);
    }
}
