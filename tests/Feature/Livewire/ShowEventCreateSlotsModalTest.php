<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventCreateSlotsModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_open_create_slots_modal_and_shell_includes_dialog(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('id="event-slots-create-modal"')
            ->call('openSlotCreateModal')
            ->assertSet('slotCreateModalReady', true)
            ->assertSeeHtml('id="event-slots-create-modal"');
    }

    public function test_guest_shell_does_not_render_create_slots_modal(): void
    {
        $event = Event::factory()->create();

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertDontSeeHtml('id="event-slots-create-modal"');
    }
}
