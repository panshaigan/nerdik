<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\EventOrganizerFlagRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventOrganizerFlagRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    private EventOrganizerFlagRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(EventOrganizerFlagRequestHandler::class);
    }

    #[Test]
    public function type_is_event_organizer_flag(): void
    {
        $this->assertSame(UserRequestType::EventOrganizerFlag, $this->handler->type());
    }

    #[Test]
    public function assert_can_respond_requires_admin(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->assertCanRespond($request, $nonAdmin);
    }

    #[Test]
    public function assert_can_respond_allows_admin(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);

        $this->handler->assertCanRespond($request, $admin);
        $this->assertTrue(true);
    }

    #[Test]
    public function apply_grants_event_organizer_flag_to_requester(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);
        $request->load('requester');

        $outcome = $this->handler->apply($request);

        $this->assertNull($outcome);
        $this->assertTrue($requester->fresh()->canCreateEvents());
    }

    #[Test]
    public function apply_is_noop_when_requester_already_can_create_events(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => true]);
        $request = UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
        ]);
        $request->load('requester');

        $outcome = $this->handler->apply($request);

        $this->assertNull($outcome);
        $this->assertTrue($requester->fresh()->canCreateEvents());
    }

    #[Test]
    public function apply_rejects_missing_requester(): void
    {
        $request = UserRequest::factory()->eventOrganizerFlag()->create();
        $request->setRelation('requester', null);

        $this->expectValidationKey(
            fn () => $this->handler->apply($request),
            'request',
        );
    }

    #[Test]
    public function assert_can_send_rejects_subject_or_recipient(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, null, Organization::factory()->create()),
            'subject',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, User::factory()->create(), null),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_rejects_existing_organizer(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => true]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, null, null),
            'requester',
        );
    }

    #[Test]
    public function assert_can_send_rejects_during_decline_cooldown(): void
    {
        config(['user_requests.organizer_request_cooldown_days' => 30]);
        $requester = User::factory()->create(['is_event_organizer' => false]);

        UserRequest::factory()->eventOrganizerFlag()->create([
            'requester_id' => $requester->id,
            'status' => UserRequestStatus::Declined,
            'responded_at' => now()->subDays(1),
        ]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($requester, null, null),
            'requester',
        );
    }

    #[Test]
    public function assert_can_send_allows_eligible_requester(): void
    {
        $requester = User::factory()->create(['is_event_organizer' => false]);

        $this->handler->assertCanSend($requester, null, null);
        $this->assertTrue(true);
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
