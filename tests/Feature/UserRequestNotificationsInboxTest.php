<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationList;
use App\Livewire\Notifications\RespondToUserRequest;
use App\Livewire\UserRequests\IncomingUserRequestList;
use App\Livewire\UserRequests\OutgoingUserRequestList;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestNotificationsInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_list_shows_pending_received_request(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Board Game Night',
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'message' => 'Come play with us',
        ]);

        Livewire::actingAs($recipient)
            ->test(IncomingUserRequestList::class)
            ->assertSee($host->displayName())
            ->assertSee('Board Game Night')
            ->assertSee('Come play with us');
    }

    public function test_outgoing_list_shows_sent_request_with_subject(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Morning Yoga',
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        Livewire::actingAs($host)
            ->test(OutgoingUserRequestList::class)
            ->assertSee($recipient->displayName())
            ->assertSee('Morning Yoga');
    }

    public function test_respond_button_opens_modal(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $request = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        Livewire::actingAs($recipient)
            ->test(IncomingUserRequestList::class)
            ->call('respond', $request->id)
            ->assertDispatched('open-user-request-modal', requestId: $request->id);
    }

    public function test_admin_sees_organizer_flag_request_without_recipient(): void
    {
        $requester = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);

        Livewire::actingAs($admin)
            ->test(IncomingUserRequestList::class)
            ->assertSee($requester->displayName());
    }

    public function test_notification_list_shows_incoming_before_timeline(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Chess Club',
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        $html = Livewire::actingAs($recipient)
            ->test(NotificationList::class)
            ->assertSeeHtml('data-ui="incoming-user-requests"')
            ->assertSeeHtml('data-ui="outgoing-user-requests"')
            ->html();

        $incomingPos = strpos($html, 'data-ui="incoming-user-requests"');
        $timelinePos = strpos($html, __('ui.notifications.timeline_heading'));

        $this->assertNotFalse($incomingPos);
        $this->assertNotFalse($timelinePos);
        $this->assertLessThan($timelinePos, $incomingPos);
    }

    public function test_open_user_request_modal_event_opens_respond_modal(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Test Org']);

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);

        Livewire::actingAs($recipient)
            ->test(RespondToUserRequest::class)
            ->dispatch('open-user-request-modal', requestId: $request->id)
            ->assertSet('open', true)
            ->assertSet('requestId', $request->id)
            ->assertSee('Test Org');
    }
}
