<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventDefaultTabTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_to_description_before_event_without_enrollment_or_proposals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'description');
    }

    public function test_defaults_to_plan_during_active_enrollment_window_for_non_organizer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        $this->createActiveEnrollmentWindow($event);

        Livewire::actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'plan');
    }

    public function test_defaults_to_plan_while_event_is_in_progress_outside_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 14:00:00', 'UTC'));
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'plan');
    }

    public function test_defaults_to_proposals_for_organizer_with_pending_proposal_before_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $activity = Activity::factory()->proposed()->create(['created_by' => $proposer->id]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'proposals')
            ->assertSeeHtml('data-ui="event-show-tab-proposals"');
    }

    public function test_proposals_default_takes_priority_over_plan_during_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $this->createActiveEnrollmentWindow($event);
        $activity = Activity::factory()->proposed()->create(['created_by' => $proposer->id]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'proposals');
    }

    public function test_explicit_tab_query_param_description_wins_during_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $this->createActiveEnrollmentWindow($event);

        Livewire::withQueryParams(['tab' => 'description'])
            ->actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'description');
    }

    public function test_explicit_tab_query_param_plan_wins_during_enrollment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $this->createActiveEnrollmentWindow($event);

        Livewire::withQueryParams(['tab' => 'plan'])
            ->actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'plan');
    }

    private function createActiveEnrollmentWindow(Event $event): void
    {
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);
    }
}
