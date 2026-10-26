<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\UserRequestReceivedNotification;
use App\Notifications\UserRequestResolvedNotification;
use App\Services\UserRequests\UserRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function send_creates_pending_request_and_notifies_recipient(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $recipient = User::factory()->create(['organization_id' => null]);

        $request = app(UserRequestService::class)->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
            '  Join us  ',
        );

        $this->assertSame(UserRequestStatus::Pending, $request->status);
        $this->assertSame('Join us', $request->message);
        Notification::assertSentTo($recipient, UserRequestReceivedNotification::class);
    }

    #[Test]
    public function send_rejects_blank_message_as_null_and_rejects_too_long_message(): void
    {
        Notification::fake();
        config(['user_requests.message_max_length' => 10]);

        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $recipient = User::factory()->create(['organization_id' => null]);
        $service = app(UserRequestService::class);

        $blank = $service->send(
            UserRequestType::OrganizationInvite,
            $owner,
            $recipient,
            $organization,
            '   ',
        );
        $this->assertNull($blank->message);

        try {
            $service->send(
                UserRequestType::OrganizationInvite,
                $owner,
                User::factory()->create(['organization_id' => null]),
                $organization,
                str_repeat('a', 11),
            );
            $this->fail('Expected ValidationException for long message.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('message', $e->errors());
        }
    }

    #[Test]
    public function cancel_marks_pending_request_cancelled_and_notifies(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $recipient = User::factory()->create();
        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);

        $cancelled = app(UserRequestService::class)->cancel($request, $owner);

        $this->assertSame(UserRequestStatus::Cancelled, $cancelled->status);
        $this->assertSame($owner->id, (int) $cancelled->responded_by_id);
        Notification::assertSentTo($owner, UserRequestResolvedNotification::class);
        Notification::assertSentTo($recipient, UserRequestResolvedNotification::class);
    }

    #[Test]
    public function cancel_rejects_non_pending_and_non_requester(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $pending = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => User::factory()->create()->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $accepted = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => User::factory()->create()->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'status' => UserRequestStatus::Accepted,
            'responded_at' => now(),
        ]);
        $service = app(UserRequestService::class);

        try {
            $service->cancel($accepted, $owner);
            $this->fail('Expected ValidationException for already resolved.');
        } catch (ValidationException $e) {
            $this->assertSame(__('ui.user_requests.already_resolved'), $e->errors()['request'][0]);
        }

        try {
            $service->cancel($pending, $stranger);
            $this->fail('Expected ValidationException for cancel unauthorized.');
        } catch (ValidationException $e) {
            $this->assertSame(__('ui.user_requests.cancel_unauthorized'), $e->errors()['request'][0]);
        }
    }
}
