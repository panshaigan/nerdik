<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Livewire\Activities\ShowActivity;
use App\Livewire\UserRequests\InviteUserRequest;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestInviteParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_sees_invite_button_on_participation_tab(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertSeeHtml('wire:name="user-requests.invite-user-request"');
    }

    public function test_non_host_does_not_see_invite_button(): void
    {
        $host = User::factory()->create();
        $visitor = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        Livewire::actingAs($visitor)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertDontSeeHtml('wire:name="user-requests.invite-user-request"');
    }

    public function test_search_finds_user_by_nickname(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create(['nickname' => 'uniqueinvitee99']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        Livewire::actingAs($host)
            ->test(InviteUserRequest::class, [
                'type' => 'activity_invite',
                'subjectId' => $activity->id,
            ])
            ->call('openModal')
            ->call('search', 'uniqueinv')
            ->assertSet('lastSearchTerm', 'uniqueinv')
            ->assertSet('userOptions', fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['id'] === $invitee->id
                    && $option['name'] === $invitee->nickname
                    && isset($option['avatar']),
            ));
    }

    public function test_selected_user_id_is_cast_to_integer(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        Livewire::actingAs($host)
            ->test(InviteUserRequest::class, [
                'type' => 'activity_invite',
                'subjectId' => $activity->id,
            ])
            ->set('selectedUserId', '42')
            ->assertSet('selectedUserId', 42);
    }

    public function test_send_creates_pending_activity_invite(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => false,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        Livewire::actingAs($host)
            ->test(InviteUserRequest::class, [
                'type' => 'activity_invite',
                'subjectId' => $activity->id,
            ])
            ->set('selectedUserId', $invitee->id)
            ->set('message', 'Join us!')
            ->call('send')
            ->assertDispatched('user-request-sent');

        $this->assertDatabaseHas('user_requests', [
            'type' => UserRequestType::ActivityInvite->value,
            'status' => UserRequestStatus::Pending->value,
            'requester_id' => $host->id,
            'recipient_id' => $invitee->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'message' => 'Join us!',
        ]);
    }

    public function test_cannot_invite_existing_participant(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['nickname' => 'alreadyjoined']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        Livewire::actingAs($host)
            ->test(InviteUserRequest::class, [
                'type' => 'activity_invite',
                'subjectId' => $activity->id,
            ])
            ->call('search', 'alreadyjoined')
            ->assertSet('userOptions', []);
    }
}
