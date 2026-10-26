<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\EventOrganizerFlagRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventOrganizerFlagRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_event_organizer_flag(): void
    {
        $handler = app(EventOrganizerFlagRequestHandler::class);

        $this->assertSame(UserRequestType::EventOrganizerFlag, $handler->type());
    }

    public function test_assert_can_respond_requires_admin(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);

        $handler = app(EventOrganizerFlagRequestHandler::class);

        $this->expectException(ValidationException::class);

        $handler->assertCanRespond($request, $nonAdmin);
    }

    public function test_apply_grants_event_organizer_flag_to_requester(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);
        $request->load('requester');

        $handler = app(EventOrganizerFlagRequestHandler::class);
        $outcome = $handler->apply($request);

        $this->assertNull($outcome);
        $this->assertTrue($requester->fresh()->canCreateEvents());
    }
}
