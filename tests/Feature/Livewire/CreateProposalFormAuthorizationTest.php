<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ActivityProposals\CreateProposalForm;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateProposalFormAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_proposal_for_activity_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->public()->create();
        $foreignActivity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($stranger)
            ->test(CreateProposalForm::class, ['event' => $event])
            ->set('activity_id', $foreignActivity->id)
            ->call('save')
            ->assertHasErrors(['activity_id']);

        $this->assertSame(0, ActivityProposal::query()->count());
    }

    public function test_proposal_slots_are_limited_to_selected_event_free_slots(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $otherEvent = Event::factory()->public()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $validSlot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'requires_approval' => true,
        ]);

        $occupiedSlot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => Activity::factory()->create()->id,
            'requires_approval' => true,
        ]);

        $foreignSlot = Slot::factory()->create([
            'event_id' => $otherEvent->id,
            'activity_id' => null,
            'requires_approval' => true,
        ]);

        Livewire::actingAs($user)
            ->test(CreateProposalForm::class, ['event' => $event])
            ->set('activity_id', $activity->id)
            ->set('slot_ids', [$validSlot->id, $occupiedSlot->id, $foreignSlot->id])
            ->call('save')
            ->assertHasNoErrors();

        $proposal = ActivityProposal::query()->latest('id')->firstOrFail();
        $attachedSlotIds = $proposal->proposedSlots()->pluck('slots.id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$validSlot->id], $attachedSlotIds);
    }
}
