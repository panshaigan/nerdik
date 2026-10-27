<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\ManageEventForm;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventMaintainersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function maintainer_can_download_event_participants_pdf(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);

        $this->actingAs($maintainer)
            ->get(route('events.participants.pdf', $event))
            ->assertOk();
    }

    #[Test]
    public function stranger_cannot_download_event_participants_pdf(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('events.participants.pdf', $event))
            ->assertForbidden();
    }

    #[Test]
    public function maintainer_can_view_private_event(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $event = Event::factory()->private()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
            'slug' => 'private-maintainer-event',
        ]);

        $this->actingAs($maintainer)
            ->get(route('events.show', $event))
            ->assertOk();

        auth()->logout();

        $this->get(route('events.show', $event))
            ->assertNotFound();
    }

    #[Test]
    public function maintainer_can_delete_slot_created_by_owner(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($maintainer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('deleteSlot', $slot->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('slots', ['id' => $slot->id]);
    }

    #[Test]
    public function owner_can_add_and_remove_maintainer(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['nickname' => 'coorg-candidate']);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('lastSearchTerm', 'coorg')
            ->assertSet('userOptions', fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['id'] === $candidate->id
                    && $option['name'] === $candidate->nickname,
            ))
            ->call('selectMaintainerCandidate', $candidate->id)
            ->assertHasNoErrors()
            ->assertSet('selectedMaintainerOption', null);

        $this->assertTrue($event->fresh('maintainers')->maintainers->contains($candidate));

        Livewire::actingAs($owner)
            ->test(ManageEventForm::class, ['event' => $event->fresh('maintainers')])
            ->call('removeMaintainer', $candidate->id)
            ->assertHasNoErrors();

        $this->assertFalse($event->fresh('maintainers')->maintainers->contains($candidate));
    }

    #[Test]
    public function saving_the_event_persists_a_pending_co_organizer(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['nickname' => 'pending-coorg']);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('selectedMaintainerOption', [
                'id' => $candidate->id,
                'name' => $candidate->nickname,
                'display_name' => $candidate->displayName(),
                'avatar' => $candidate->avatarUrl(),
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('events.show', $event));

        $this->assertTrue($event->fresh('maintainers')->maintainers->contains($candidate));
    }

    #[Test]
    public function maintainer_without_organizer_flag_sees_event_manage_controls(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create([
            'is_event_organizer' => false,
        ]);
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);

        $this->assertTrue($maintainer->canManageEvent($event->fresh()));

        Livewire::actingAs($maintainer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-manage"')
            ->assertSeeHtml('data-ui="event-show-edit-open"');

        $this->actingAs($maintainer)
            ->get(route('events.edit', $event))
            ->assertOk();

        $this->assertTrue(app(BrowseListingCardPresenter::class)->fromEvent($event, [])->canEdit);
    }

    #[Test]
    public function maintainer_cannot_add_another_maintainer(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);

        Livewire::actingAs($maintainer)
            ->test(ManageEventForm::class, ['event' => $event])
            ->assertDontSee(__('ui.events.maintainers.heading'), false);
    }

    #[Test]
    public function maintainer_can_reject_proposal_via_http(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->public()->withMaintainer($maintainer)->create([
            'created_by' => $owner->id,
        ]);
        $activity = Activity::factory()->proposed()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'status' => ActivityProposalStatus::Pending,
            'created_by' => $host->id,
        ]);

        $this->actingAs($maintainer)
            ->post(route('activity-proposals.reject', $proposal))
            ->assertRedirect();

        $this->assertSame(ActivityProposalStatus::Rejected, $proposal->fresh()->status);
    }
}
