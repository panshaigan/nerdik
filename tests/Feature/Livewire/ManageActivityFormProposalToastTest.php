<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivityFormProposalToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_activity_with_proposal_toasts_and_redirects_to_event(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['name' => 'Toast Event']);
        $activityTypeId = (int) ActivityType::query()->value('id');
        $name = 'Proposed activity for toast';
        $message = __('ui.status.activity_saved_with_proposal', ['event' => $event->name]);

        Livewire::actingAs($user)
            ->withQueryParams(['proposal_event_id' => $event->id])
            ->test(ManageActivityForm::class)
            ->set('name', $name)
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
            ->set('proposal_event_id', $event->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('mary.toast.title', $message);

        $activity = Activity::query()->where('name', $name)->first();
        $this->assertNotNull($activity);
        $this->assertSame(1, ActivityProposal::query()->where('activity_id', $activity->id)->count());
    }
}
