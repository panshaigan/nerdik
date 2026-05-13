<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Livewire\ActivityProposals\ProposalIndex;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\EventShowProposalsTab;
use App\Livewire\Notifications\NotificationList;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposal_index_only_shows_proposals_owned_by_user_or_users_events(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedEvent = Event::factory()->create(['created_by' => $user->id]);
        $foreignEvent = Event::factory()->create(['created_by' => $otherUser->id]);

        $activity = Activity::factory()->create(['created_by' => $otherUser->id]);

        $userOwnedProposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $foreignEvent->id,
            'created_by' => $user->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $proposalForOwnedEvent = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $ownedEvent->id,
            'created_by' => $otherUser->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $foreignEvent->id,
            'created_by' => $otherUser->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        /** @var Collection<int, ActivityProposal> $proposals */
        $proposals = Livewire::actingAs($user)
            ->test(ProposalIndex::class)
            ->viewData('proposals');

        $this->assertCount(2, $proposals);
        $this->assertTrue($proposals->contains('id', $userOwnedProposal->id));
        $this->assertTrue($proposals->contains('id', $proposalForOwnedEvent->id));
    }

    public function test_accept_pending_proposal_validates_slot_id_before_use(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherEvent = Event::factory()->public()->create();
        $activity = Activity::factory()->proposed()->create(['created_by' => $owner->id]);

        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $foreignSlot = Slot::factory()->create([
            'event_id' => $otherEvent->id,
            'activity_id' => null,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowProposalsTab::class, ['eventId' => $event->id])
            ->set("proposalAcceptSlotId.{$proposal->id}", $foreignSlot->id)
            ->call('acceptPendingProposal', $proposal->id)
            ->assertHasErrors("proposalAcceptSlot.{$proposal->id}");

        $this->assertSame(ActivityProposalStatus::Pending, $proposal->fresh()->status);
    }

    public function test_cancel_slot_activity_validates_cancel_reason_length(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create(['created_by' => $owner->id]);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->set("slotCancelReason.{$slot->id}", str_repeat('a', 1001))
            ->call('cancelSlotActivity', $slot->id)
            ->assertHasErrors("slotCancelReason.{$slot->id}");

        $this->assertNull($activity->fresh()->cancelled_at);
    }

    public function test_notification_redirect_allows_internal_paths_only(): void
    {
        $user = User::factory()->create();

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['url' => 'https://evil.example.test/phish'];
            }
        });

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['url' => '/profile'];
            }
        });

        $notifications = $user->notifications()->latest()->get();
        $internalId = $notifications->firstWhere('data.url', '/profile')?->id;
        $externalId = $notifications->firstWhere('data.url', 'https://evil.example.test/phish')?->id;

        $this->assertNotNull($internalId);
        $this->assertNotNull($externalId);

        Livewire::actingAs($user)
            ->test(NotificationList::class)
            ->call('markReadAndGo', $externalId)
            ->assertRedirect(route('dashboard'));

        Livewire::actingAs($user)
            ->test(NotificationList::class)
            ->call('markReadAndGo', $internalId)
            ->assertRedirect('/profile');
    }
}
