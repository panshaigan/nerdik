<?php

namespace App\Livewire\Slots;

use App\Models\Slot;
use App\Traits\AuthorizesOwnership;
use Livewire\Attributes\On;
use Livewire\Component;

class SlotIndex extends Component
{
    use AuthorizesOwnership;

    /** Bumped when slots change via async modal submit (shared with event show). */
    public int $slotListVersion = 0;

    #[On('slot-mutations-refresh')]
    public function refreshAfterSlotMutation(): void
    {
        $this->slotListVersion++;
    }

    public function deleteSlot(int $slotId): void
    {
        $slot = Slot::query()->findOrFail($slotId);
        $this->authorizeCreatedBy($slot);
        $slot->delete();
        session()->flash('status', __('Slot deleted.'));
    }

    public function render()
    {
        $slots = Slot::with(['event', 'place.parent'])
            ->orderBy('starts_at')
            ->get();

        return view('livewire.slots.slot-index', [
            'slots' => $slots,
        ]);
    }
}
