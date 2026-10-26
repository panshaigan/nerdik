<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\OrganizationJoinRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrganizationJoinRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationJoinRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(OrganizationJoinRequestHandler::class);
    }

    #[Test]
    public function type_is_organization_join_request(): void
    {
        $this->assertSame(UserRequestType::OrganizationJoinRequest, $this->handler->type());
    }

    #[Test]
    public function apply_assigns_requester_to_organization(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $owner->id]);
        $requester = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $owner->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request->load(['subject', 'requester']);

        $outcome = $this->handler->apply($request);

        $this->assertNull($outcome);
        $this->assertSame((int) $organization->id, (int) $requester->fresh()->organization_id);
    }

    #[Test]
    public function assert_can_respond_requires_organization_manager(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $owner->id]);
        $outsider = User::factory()->create();
        $requester = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $owner->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request->load('subject');

        $this->expectException(ValidationException::class);
        $this->handler->assertCanRespond($request, $outsider);
    }

    #[Test]
    public function assert_can_respond_allows_organization_owner(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $requester = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $owner->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request->load('subject');

        $this->handler->assertCanRespond($request, $owner);
        $this->assertTrue(true);
    }

    #[Test]
    public function assert_can_respond_rejects_recipient_without_org_manage_rights(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $staleRecipient = User::factory()->create();
        $requester = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $staleRecipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request->load('subject');

        $this->expectValidationKey(
            fn () => $this->handler->assertCanRespond($request, $staleRecipient),
            'request',
        );

        $request->setRelation('subject', Activity::factory()->create());
        $this->expectValidationKey(
            fn () => $this->handler->assertCanRespond($request, $staleRecipient),
            'request',
        );
    }

    #[Test]
    public function assert_can_send_rejects_invalid_subject_missing_recipient_and_self(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $requester = User::factory()->create(['organization_id' => null]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, $owner, Activity::factory()->create()),
            'subject',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, null, $organization),
            'recipient',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($owner, $owner, $organization),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_rejects_unauthorized_recipient_and_existing_member(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $stranger = User::factory()->create();
        $member = User::factory()->create(['organization_id' => $organization->id]);
        $outsider = User::factory()->create(['organization_id' => null]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($outsider, $stranger, $organization),
            'recipient',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($member, $owner, $organization),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_allows_outsider_to_request_owner(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $outsider = User::factory()->create(['organization_id' => null]);

        $this->handler->assertCanSend($outsider, $owner, $organization);
        $this->assertTrue(true);
    }

    #[Test]
    public function apply_rejects_missing_requester_or_invalid_subject(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['created_by' => $owner->id]);
        $request = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => User::factory()->create()->id,
            'recipient_id' => $owner->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request->setRelation('requester', null);
        $request->load('subject');

        $this->expectValidationKey(
            fn () => $this->handler->apply($request),
            'request',
        );

        $request2 = UserRequest::factory()->organizationJoinRequest()->create([
            'requester_id' => User::factory()->create()->id,
            'recipient_id' => $owner->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
        $request2->load('requester');
        $request2->setRelation('subject', Activity::factory()->create());

        $this->expectValidationKey(
            fn () => $this->handler->apply($request2),
            'request',
        );
    }

    private function expectValidationKey(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail("Expected ValidationException with key [{$key}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
