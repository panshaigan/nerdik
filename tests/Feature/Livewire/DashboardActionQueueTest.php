<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\Dashboard\DashboardActionQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardActionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_queue_is_empty_for_user_with_no_pending_items(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], app(DashboardActionQueueService::class)->itemsFor($user));

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertDontSeeHtml('data-ui="dashboard-needs-action"');
    }

    public function test_incoming_user_request_appears_on_dashboard(): void
    {
        $recipient = User::factory()->create();
        $requester = User::factory()->create();
        $activity = Activity::factory()->create();

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'subject_type' => (new Activity)->getMorphClass(),
            'subject_id' => $activity->id,
        ]);

        Livewire::actingAs($recipient)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-incoming_request"')
            ->assertSee((string) $activity->name);
    }

    public function test_organizer_pending_proposal_appears_on_dashboard(): void
    {
        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $activity = Activity::factory()->create(['created_by' => $host->id, 'name' => 'Queue Review Activity']);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $host->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Livewire::actingAs($organizer)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-proposal_to_review"')
            ->assertSee('Queue Review Activity');
    }

    public function test_host_waitlist_approval_surfaces_for_upcoming_activity(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Queue Waitlist Host',
            'participation_mode' => ParticipationMode::HostApproval,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
        ]);

        ActivityWaitlistEntry::factory()->create([
            'activity_id' => $activity->id,
            'user_id' => $player->id,
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-host_waitlist"')
            ->assertSee('Queue Waitlist Host');
    }

    public function test_user_waitlist_entry_surfaces_on_dashboard(): void
    {
        $player = User::factory()->create();
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Queue Player Waitlist',
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
        ]);

        ActivityWaitlistEntry::factory()->create([
            'activity_id' => $activity->id,
            'user_id' => $player->id,
        ]);

        Livewire::actingAs($player)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-user_waitlist"')
            ->assertSee('Queue Player Waitlist');
    }

    public function test_my_pending_proposal_appears_on_dashboard(): void
    {
        $host = User::factory()->create();
        $event = Event::factory()->create(['name' => 'Queue Awaiting Event']);
        $activity = Activity::factory()->create(['created_by' => $host->id]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $host->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-my_pending_proposal"')
            ->assertSee('Queue Awaiting Event');
    }

    public function test_upcoming_lottery_draw_appears_for_hosted_lottery_activity(): void
    {
        $host = User::factory()->create();
        $startsAt = now()->addDays(3);

        Activity::factory()->lottery()->create([
            'created_by' => $host->id,
            'name' => 'Queue Lottery Draw',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'lottery_resolved_at' => null,
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-lottery_draw"')
            ->assertSee('Queue Lottery Draw');
    }
}
