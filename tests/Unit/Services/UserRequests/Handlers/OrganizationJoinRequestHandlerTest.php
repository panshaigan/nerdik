<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\OrganizationJoinRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrganizationJoinRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_organization_join_request(): void
    {
        $handler = app(OrganizationJoinRequestHandler::class);

        $this->assertSame(UserRequestType::OrganizationJoinRequest, $handler->type());
    }

    public function test_apply_assigns_requester_to_organization(): void
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

        $handler = app(OrganizationJoinRequestHandler::class);
        $outcome = $handler->apply($request);

        $this->assertNull($outcome);
        $this->assertSame((int) $organization->id, (int) $requester->fresh()->organization_id);
    }

    public function test_assert_can_respond_requires_organization_manager(): void
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

        $handler = app(OrganizationJoinRequestHandler::class);

        $this->expectException(ValidationException::class);

        $handler->assertCanRespond($request, $outsider);
    }
}
