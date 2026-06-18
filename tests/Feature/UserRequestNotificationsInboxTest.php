<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationList;
use App\Livewire\Notifications\RespondToUserRequest;
use App\Livewire\UserRequests\IncomingUserRequestList;
use App\Livewire\UserRequests\OutgoingUserRequestList;
use App\Livewire\UserRequests\UserRequestInbox;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\UserRequestReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestNotificationsInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_page_renders_inbox_sections(): void
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

        $this->actingAs($recipient)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee(__('ui.requests.page_title'))
            ->assertSeeHtml('data-ui="incoming-user-requests"')
            ->assertSeeHtml('data-ui="outgoing-user-requests"');
    }

    public function test_notifications_page_does_not_render_request_lists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSeeHtml('data-ui="incoming-user-requests"')
            ->assertDontSeeHtml('data-ui="outgoing-user-requests"')
            ->assertSee(__('ui.notifications.timeline_heading'));
    }

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

    public function test_incoming_list_renders_activity_subject_as_link(): void
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
        ]);

        Livewire::actingAs($recipient)
            ->test(IncomingUserRequestList::class)
            ->assertSeeHtml('href="'.route('activities.show', $activity).'"')
            ->assertSeeHtml('wire:navigate');
    }

    public function test_incoming_list_renders_organization_subject_as_badge(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Test Org']);

        UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);

        Livewire::actingAs($recipient)
            ->test(IncomingUserRequestList::class)
            ->assertSeeHtml('data-ui="organization-badge-contact"')
            ->assertSee('Test Org');
    }

    public function test_incoming_list_centers_action_buttons(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'message' => 'Long message that wraps to multiple lines',
        ]);

        Livewire::actingAs($recipient)
            ->test(IncomingUserRequestList::class)
            ->assertSeeHtml('flex items-center justify-between');
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

    public function test_user_request_inbox_opens_modal_from_url_parameter(): void
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
            ->withQueryParams(['request' => $request->id])
            ->test(UserRequestInbox::class)
            ->assertDispatched('open-user-request-modal', requestId: $request->id);
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

    public function test_actionable_notification_click_redirects_to_requests_page(): void
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

        $recipient->notify(new UserRequestReceivedNotification($request));
        $notification = $recipient->fresh()->notifications()->firstOrFail();

        Livewire::actingAs($recipient)
            ->test(NotificationList::class)
            ->call('handleNotificationClick', $notification->id)
            ->assertRedirect(route('requests.index', ['request' => $request->id]));
    }
}
