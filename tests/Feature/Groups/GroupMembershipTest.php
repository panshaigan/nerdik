<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\Group;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\GroupMembershipService;
use App\Services\UserRequests\Handlers\GroupInviteRequestHandler;
use App\Services\UserRequests\UserRequestDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class GroupMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_group_attaches_owner_as_member(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Tuesday RPG');

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'created_by' => $owner->id]);
        $this->assertTrue($group->users()->whereKey($owner->id)->exists());
    }

    public function test_non_owner_cannot_invite_via_handler(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $target = User::factory()->create();

        $group = Group::factory()->withOwnerMember()->create(['created_by' => $owner->id]);
        $group->users()->attach($member->id);

        $handler = app(GroupInviteRequestHandler::class);

        $this->expectException(ValidationException::class);
        $handler->assertCanSend($outsider, $target, $group);
    }

    public function test_accepting_group_invite_adds_member(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Campaign');

        $request = UserRequest::query()->create([
            'type' => UserRequestType::GroupInvite,
            'status' => UserRequestStatus::Pending,
            'requester_id' => $owner->id,
            'recipient_id' => $invitee->id,
            'subject_type' => 'group',
            'subject_id' => $group->id,
        ]);

        app(UserRequestDecisionService::class)->accept($request, $invitee);

        $this->assertTrue($group->fresh()->users()->whereKey($invitee->id)->exists());
    }

    public function test_owner_cannot_leave_group(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Solo owner');

        $this->expectException(ValidationException::class);
        app(GroupMembershipService::class)->leave($group, $owner);
    }

    public function test_member_can_leave_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Party');
        $group->users()->attach($member->id);

        app(GroupMembershipService::class)->leave($group, $member);

        $this->assertFalse($group->fresh()->users()->whereKey($member->id)->exists());
    }
}
