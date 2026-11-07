<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GroupShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Campaign Alpha');
        $group->users()->attach($member->id);

        $this->actingAs($member)
            ->get(route('groups.show', $group))
            ->assertOk();
    }

    public function test_non_member_cannot_view_group(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Secret Party');

        $this->actingAs($outsider)
            ->get(route('groups.show', $group))
            ->assertNotFound();
    }
}
