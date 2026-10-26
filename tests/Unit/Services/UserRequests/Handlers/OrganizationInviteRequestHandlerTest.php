<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\OrganizationInviteRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationInviteRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_organization_invite(): void
    {
        $handler = app(OrganizationInviteRequestHandler::class);

        $this->assertSame(UserRequestType::OrganizationInvite, $handler->type());
    }

    public function test_apply_assigns_recipient_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $recipient = User::factory()->create(['organization_id' => null]);
        $request = UserRequest::factory()->organizationInvite()->create([
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'recipient_id' => $recipient->id,
        ]);
        $request->load(['subject', 'recipient']);

        $handler = app(OrganizationInviteRequestHandler::class);
        $outcome = $handler->apply($request);

        $this->assertNull($outcome);
        $this->assertSame((int) $organization->id, (int) $recipient->fresh()->organization_id);
    }
}
