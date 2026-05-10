<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowPlanTab;
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

        $html = Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->html();

        $this->assertStringContainsString('$wire.entangle(\'proposalSlotIds\')', $html);
        $this->assertStringNotContainsString('entangle(\'proposalSlotIds\').live', $html);
        $this->assertStringContainsString('proposeActivityHref()', $html);
        $this->assertStringContainsString('proposeActivityBaseUrl:', $html);
    }
}
