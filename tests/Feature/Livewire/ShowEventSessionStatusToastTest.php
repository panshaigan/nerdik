<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventSessionStatusToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_show_converts_session_status_to_ui_toast(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $message = __('ui.status.proposal_submitted');

        $this->actingAs($user);
        session()->flash('status', $message);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertSessionHas('ui.toast', [
                'type' => 'success',
                'title' => $message,
            ])
            ->assertSessionMissing('status');
    }

    public function test_event_show_page_fires_toast_from_flashed_status(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $message = __('ui.status.activity_saved_with_proposal', ['event' => $event->name]);

        $this
            ->actingAs($user)
            ->withSession(['status' => $message])
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee($message, false)
            ->assertSee('window.toast', false);
    }
}
