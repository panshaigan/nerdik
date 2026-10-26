<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserRequests\Handlers\ActivityInviteRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ActivityInviteRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_activity_invite(): void
    {
        $handler = app(ActivityInviteRequestHandler::class);

        $this->assertSame(UserRequestType::ActivityInvite, $handler->type());
    }

    public function test_assert_can_respond_requires_recipient(): void
    {
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();
        $request = UserRequest::factory()->activityInvite()->create([
            'recipient_id' => $recipient->id,
        ]);

        $handler = app(ActivityInviteRequestHandler::class);

        $this->expectException(ValidationException::class);

        $handler->assertCanRespond($request, $otherUser);
    }
}
