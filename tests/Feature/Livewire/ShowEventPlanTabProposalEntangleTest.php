<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabProposalEntangleTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_tab_defers_proposal_slot_entangle_without_live_sync(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);

        $html = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->html();

        $this->assertStringContainsString('$wire.entangle(\'proposalSlotIds\')', $html);
        $this->assertStringNotContainsString('entangle(\'proposalSlotIds\').live', $html);
        $this->assertStringContainsString('proposeActivityHref()', $html);
        $this->assertStringContainsString('proposeActivityBaseUrl:', $html);
    }
}
