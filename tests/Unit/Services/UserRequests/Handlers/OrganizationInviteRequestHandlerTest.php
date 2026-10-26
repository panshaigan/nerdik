<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\OrganizationInviteRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrganizationInviteRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationInviteRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(OrganizationInviteRequestHandler::class);
    }

    #[Test]
    public function type_is_organization_invite(): void
    {
        $this->assertSame(UserRequestType::OrganizationInvite, $this->handler->type());
    }

    #[Test]
    public function apply_assigns_recipient_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $recipient = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationInvite()->create([
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'recipient_id' => $recipient->id,
        ]);
        $request->load(['subject', 'recipient']);

        $outcome = $this->handler->apply($request);

        $this->assertNull($outcome);
        $this->assertSame((int) $organization->id, (int) $recipient->fresh()->organization_id);
    }

    #[Test]
    public function assert_can_send_rejects_invalid_subject_missing_recipient_and_self(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($owner, User::factory()->create(), Activity::factory()->create()),
            'subject',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($owner, null, $organization),
            'recipient',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($owner, $owner, $organization),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_rejects_unauthorized_owner_and_existing_member(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $member = User::factory()->create(['organization_id' => $organization->id]);
        $outsider = User::factory()->create(['organization_id' => null]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($stranger, $outsider, $organization),
            'subject',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($owner, $member, $organization),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_allows_owner_to_invite_outsider(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $outsider = User::factory()->create(['organization_id' => null]);

        $this->handler->assertCanSend($owner, $outsider, $organization);
        $this->assertTrue(true);
    }

    #[Test]
    public function assert_can_respond_requires_recipient(): void
    {
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $request = UserRequest::factory()->organizationInvite()->create([
            'recipient_id' => $recipient->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->assertCanRespond($request, $other);
    }

    #[Test]
    public function apply_rejects_invalid_subject_or_recipient(): void
    {
        $request = UserRequest::factory()->organizationInvite()->create([
            'recipient_id' => null,
        ]);
        $request->setRelation('subject', Activity::factory()->create());
        $request->setRelation('recipient', null);

        $this->expectValidationKey(
            fn () => $this->handler->apply($request),
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
