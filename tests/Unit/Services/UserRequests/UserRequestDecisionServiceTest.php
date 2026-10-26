<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\UserRequestResolvedNotification;
use App\Services\UserRequests\UserRequestDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserRequestDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function accept_pending_organization_invite_sets_status_and_responded_by(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create(['organization_id' => null]);

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);

        $resolved = app(UserRequestDecisionService::class)->accept($request, $recipient);

        $this->assertSame(UserRequestStatus::Accepted, $resolved->status);
        $this->assertSame($recipient->id, (int) $resolved->responded_by_id);
        $this->assertNotNull($resolved->responded_at);
        $this->assertSame($organization->id, (int) $recipient->fresh()->organization_id);
        Notification::assertSentTo($owner, UserRequestResolvedNotification::class);
    }

    #[Test]
    public function decline_pending_request_with_note_notifies_requester(): void
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
        ]);

        $resolved = app(UserRequestDecisionService::class)->decline($request, $recipient, 'Not now');

        $this->assertSame(UserRequestStatus::Declined, $resolved->status);
        $this->assertSame($recipient->id, (int) $resolved->responded_by_id);
        $this->assertNull($recipient->fresh()->organization_id);
        Notification::assertSentTo(
            $owner,
            UserRequestResolvedNotification::class,
            function (UserRequestResolvedNotification $notification) {
                return $notification->declineNote === 'Not now';
            },
        );
    }

    #[Test]
    public function accept_already_resolved_request_throws(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'status' => UserRequestStatus::Accepted,
            'responded_at' => now(),
            'responded_by_id' => $recipient->id,
        ]);

        try {
            app(UserRequestDecisionService::class)->accept($request, $recipient);
            $this->fail('Expected ValidationException for already resolved request.');
        } catch (ValidationException $e) {
            $this->assertSame(
                __('ui.user_requests.already_resolved'),
                $e->errors()['request'][0],
            );
        }
    }

    #[Test]
    public function accept_past_expires_at_marks_expired_and_throws(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create(['organization_id' => null]);

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'expires_at' => now()->subMinute(),
        ]);

        try {
            app(UserRequestDecisionService::class)->accept($request, $recipient);
            $this->fail('Expected ValidationException for expired request.');
        } catch (ValidationException $e) {
            $this->assertSame(
                __('ui.user_requests.expired'),
                $e->errors()['request'][0],
            );
        }

        $fresh = $request->fresh();
        $this->assertSame(UserRequestStatus::Expired, $fresh->status);
        $this->assertNotNull($fresh->responded_at);
        $this->assertNull($recipient->fresh()->organization_id);
    }
}
