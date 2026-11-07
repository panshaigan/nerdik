<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivityProposalEventClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_proposal_event_search_clears_proposal_event_id(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('hosting_mode', Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
            ->set('proposal_event_id', $event->id)
            ->set('proposal_event_search', $event->name)
            ->assertSet('proposal_event_id', $event->id)
            ->set('proposal_event_search', '')
            ->assertSet('proposal_event_id', null)
            ->assertSet('proposal_event_search', '')
            ->assertSet('proposal_slot_ids', []);
    }

    public function test_clearing_proposal_event_id_preserves_search_query(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('hosting_mode', Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
            ->set('proposal_event_id', $event->id)
            ->set('proposal_event_search', 'partial query')
            ->set('proposal_event_id', null)
            ->assertSet('proposal_event_id', null)
            ->assertSet('proposal_event_search', 'partial query');
    }

    public function test_search_proposal_events_returns_matching_labels(): void
    {
        $user = User::factory()->create();
        Event::factory()->create([
            'name' => 'Focus Safe Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('hosting_mode', Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
            ->call('searchProposalEvents', 'Focus')
            ->assertReturned(fn (array $results): bool => collect($results)->contains(
                fn (array $item): bool => str_contains((string) ($item['label'] ?? ''), 'Focus Safe Event')
            ));
    }
}
