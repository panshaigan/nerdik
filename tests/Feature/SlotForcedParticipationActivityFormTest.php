<?php

namespace Tests\Feature;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SlotForcedParticipationActivityFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferred_forcing_slots_constrain_participation_modes(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        $openSlot = Slot::factory()->forcesParticipation(ParticipationMode::Open)->create([
            'event_id' => $event->id,
            'activity_id' => null,
        ]);
        $hostApprovalSlot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create([
            'event_id' => $event->id,
            'activity_id' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams([
                'proposal_event_id' => $event->id,
                'proposal_slot_ids' => [$openSlot->id, $hostApprovalSlot->id],
            ])
            ->test(ManageActivityForm::class)
            ->assertSet('participationConstrainedBySlots', true)
            ->assertSet('allowedParticipationModes', fn (array $modes): bool => $modes === ['open', 'host_approval'] || $modes === ['host_approval', 'open'])
            ->set('proposal_slot_ids', [$openSlot->id])
            ->assertSet('participationConstrainedBySlots', true)
            ->assertSet('allowedParticipationModes', ['open'])
            ->set('proposal_slot_ids', [])
            ->assertSet('participationConstrainedBySlots', false);
    }

    public function test_single_forcing_lottery_slot_locks_lottery_draw_hours(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        $slot = Slot::factory()->forcesParticipation(ParticipationMode::Lottery, 36)->create([
            'event_id' => $event->id,
            'activity_id' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams([
                'proposal_event_id' => $event->id,
                'proposal_slot_ids' => [$slot->id],
            ])
            ->test(ManageActivityForm::class)
            ->assertSet('participation_mode', ParticipationMode::Lottery->value)
            ->assertSet('lottery_draw_in_hours', 36)
            ->assertSet('lotteryDrawHoursLockedBySlots', true);
    }

    public function test_constrained_participation_mode_is_corrected_when_invalid(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        $slot = Slot::factory()->forcesParticipation(ParticipationMode::HostApproval)->create([
            'event_id' => $event->id,
            'activity_id' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams([
                'proposal_event_id' => $event->id,
                'proposal_slot_ids' => [$slot->id],
            ])
            ->test(ManageActivityForm::class)
            ->set('participation_mode', ParticipationMode::Lottery->value)
            ->assertSet('participation_mode', ParticipationMode::HostApproval->value);
    }
}
