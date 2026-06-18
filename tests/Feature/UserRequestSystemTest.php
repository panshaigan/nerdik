<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ExpireUserRequestsCommand;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Livewire\UserRequests\SendUserRequest;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\UserRequestReceivedNotification;
use App\Notifications\UserRequestResolvedNotification;
use App\Services\UserRequests\UserRequestDecisionService;
use App\Services\UserRequests\UserRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_send_user_request_component_can_open_organizer_modal(): void
    {
        $user = User::factory()->create(['is_event_organizer' => false]);

        Livewire::actingAs($user)
            ->test(SendUserRequest::class, ['type' => 'event_organizer_flag'])
            ->call('openModal')
            ->assertSet('modalOpen', true);
    }

    public function test_organization_invite_accept_moves_recipient_and_leaves_previous_org(): void
    {
        Notification::fake();

        $oldOrg = Organization::factory()->create();
        $newOrg = Organization::factory()->create();
        $owner = User::factory()->create();
        $newOrg->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create(['organization_id' => $oldOrg->id]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $newOrg,
            'Join us',
        );

        app(UserRequestDecisionService::class)->accept($request, $recipient);

        $this->assertSame($newOrg->id, $recipient->fresh()->organization_id);
        $this->assertSame(UserRequestStatus::Accepted, $request->fresh()->status);
        Notification::assertSentTo($recipient, UserRequestReceivedNotification::class);
        Notification::assertSentTo($owner, UserRequestResolvedNotification::class);
    }

    public function test_organization_join_request_accept_moves_requester(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $requester = User::factory()->create(['organization_id' => null]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationJoinRequest,
            $requester,
            $owner,
            $organization,
        );

        app(UserRequestDecisionService::class)->accept($request, $owner);

        $this->assertSame($organization->id, $requester->fresh()->organization_id);
    }

    public function test_received_request_notification_is_email_only_without_bell_entry(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        Notification::assertSentTo(
            $recipient,
            UserRequestReceivedNotification::class,
            fn (UserRequestReceivedNotification $notification, array $channels): bool => in_array('broadcast', $channels, true)
                && in_array('mail', $channels, true)
                && ! in_array('database', $channels, true),
        );
    }

    public function test_received_request_notification_does_not_create_bell_entry(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        $this->assertSame(0, $recipient->fresh()->notifications()->count());
        $this->assertSame(0, $recipient->fresh()->unreadNotifications()->count());

        $emailLog = DB::table('notification_email_logs')
            ->where('recipient_user_id', $recipient->id)
            ->where('notification_type', UserRequestReceivedNotification::class)
            ->first();

        $this->assertNotNull($emailLog);
        $this->assertSame($recipient->email, $emailLog->recipient_email);
    }

    public function test_resolved_request_notification_stays_unread(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        app(UserRequestDecisionService::class)->accept($request, $recipient);

        $resolvedNotification = $owner->fresh()->notifications()
            ->where('type', UserRequestResolvedNotification::class)
            ->firstOrFail();

        $this->assertNull($resolvedNotification->read_at);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());

        $data = $resolvedNotification->data;
        $this->assertSame('user_request_resolved', $data['type']);
        $this->assertSame($recipient->displayName(), $data['responder_name']);
        $this->assertSame($organization->name, $data['subject_label']);
        $this->assertStringContainsString($recipient->displayName(), $data['toast_description']);
        $this->assertStringContainsString(__('ui.user_requests.received_organization_invite'), $data['toast_description']);
        $this->assertStringContainsString($organization->name, $data['toast_description']);
    }

    public function test_duplicate_pending_request_is_blocked(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        $this->expectException(ValidationException::class);
        app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );
    }

    public function test_requester_can_cancel_pending_request(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        app(UserRequestService::class)->cancel($request, $owner);

        $this->assertSame(UserRequestStatus::Cancelled, $request->fresh()->status);
        Notification::assertSentTo($recipient, UserRequestResolvedNotification::class);
    }

    public function test_cancelled_request_broadcasts_to_recipient_without_bell_entry(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        app(UserRequestService::class)->cancel($request, $owner);

        Notification::assertSentTo(
            $recipient,
            UserRequestResolvedNotification::class,
            fn (UserRequestResolvedNotification $notification, array $channels): bool => in_array('broadcast', $channels, true)
                && ! in_array('database', $channels, true),
        );
    }

    public function test_cancelled_request_does_not_create_in_app_notification_for_recipient(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
        );

        app(UserRequestService::class)->cancel($request, $owner);

        $this->assertSame(0, $recipient->fresh()->notifications()->count());
        $this->assertDatabaseHas('notification_email_logs', [
            'recipient_user_id' => $recipient->id,
            'notification_type' => UserRequestResolvedNotification::class,
        ]);
    }

    public function test_activity_invite_accept_joins_when_rules_allow(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $invitee = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => false,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
            'max_participants' => 6,
        ]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::ActivityInvite,
            $host,
            $invitee,
            $activity,
        );

        app(UserRequestDecisionService::class)->accept($request, $invitee);

        $fresh = $request->fresh();
        $this->assertSame(UserRequestStatus::Accepted, $fresh->status);
        $this->assertSame(UserRequestResolutionOutcome::Joined, $fresh->resolution_outcome);
        $this->assertTrue($activity->participants()->where('user_id', $invitee->id)->exists());
    }

    public function test_activity_invite_accept_waitlists_when_approval_required(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => true,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::ActivityInvite,
            $host,
            $invitee,
            $activity,
        );

        app(UserRequestDecisionService::class)->accept($request, $invitee);

        $fresh = $request->fresh();
        $this->assertSame(UserRequestResolutionOutcome::Waitlisted, $fresh->resolution_outcome);
        $this->assertTrue($activity->waitlist()->where('user_id', $invitee->id)->exists());
    }

    public function test_activity_invite_accept_fails_outside_enrollment_window_and_stays_pending(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'requires_approval' => false,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(14, 0),
        ]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::ActivityInvite,
            $host,
            $invitee,
            $activity->fresh(['slot.event.enrollmentWindows']),
        );

        try {
            app(UserRequestDecisionService::class)->accept($request, $invitee);
            $this->fail('Expected enrollment window validation failure.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(UserRequestStatus::Pending, $request->fresh()->status);
        $this->assertFalse($activity->participants()->where('user_id', $invitee->id)->exists());
    }

    public function test_event_organizer_flag_accept_grants_access_and_notifies_requester(): void
    {
        Notification::fake();

        $requester = User::factory()->create(['is_event_organizer' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::EventOrganizerFlag,
            $requester,
            null,
            null,
        );

        Notification::assertSentTo($admin, UserRequestReceivedNotification::class);

        app(UserRequestDecisionService::class)->accept($request, $admin);

        $this->assertTrue($requester->fresh()->canCreateEvents());
        Notification::assertSentTo($requester, UserRequestResolvedNotification::class);
    }

    public function test_second_admin_cannot_accept_already_resolved_organizer_request(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $adminA = User::factory()->create(['is_admin' => true]);
        $adminB = User::factory()->create(['is_admin' => true]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::EventOrganizerFlag,
            $requester,
            null,
            null,
        );

        app(UserRequestDecisionService::class)->accept($request, $adminA);

        $this->expectException(ValidationException::class);
        app(UserRequestDecisionService::class)->accept($request->fresh(), $adminB);
    }

    public function test_expire_command_marks_pending_requests_and_notifies_parties(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan(ExpireUserRequestsCommand::class)->assertSuccessful();

        $this->assertSame(UserRequestStatus::Expired, $request->fresh()->status);
        Notification::assertSentTo($owner, UserRequestResolvedNotification::class);
        Notification::assertSentTo($recipient, UserRequestResolvedNotification::class);
    }

    public function test_expired_request_does_not_create_in_app_notifications(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan(ExpireUserRequestsCommand::class)->assertSuccessful();

        $this->assertSame(0, $owner->fresh()->notifications()->count());
        $this->assertSame(0, $recipient->fresh()->notifications()->count());
    }
}
