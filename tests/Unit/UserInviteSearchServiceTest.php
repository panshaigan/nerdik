<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserRequests\UserInviteSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInviteSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_avatar_and_nickname_for_matching_user(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create(['nickname' => 'searchableuser']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $results = app(UserInviteSearchService::class)->search(
            UserRequestType::ActivityInvite,
            $activity->id,
            'searchable',
            $host,
        );

        $this->assertCount(1, $results);
        $this->assertSame($invitee->id, $results[0]['id']);
        $this->assertSame('searchableuser', $results[0]['name']);
        $this->assertSame($invitee->displayName(), $results[0]['display_name']);
        $this->assertSame($invitee->avatarUrl(), $results[0]['avatar']);
    }

    public function test_activity_search_excludes_participants(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['nickname' => 'onroster']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        $results = app(UserInviteSearchService::class)->search(
            UserRequestType::ActivityInvite,
            $activity->id,
            'onroster',
            $host,
        );

        $this->assertSame([], $results);
    }

    public function test_organization_search_excludes_existing_members(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $member = User::factory()->create([
            'nickname' => 'orgmember',
            'organization_id' => $organization->id,
        ]);

        $results = app(UserInviteSearchService::class)->search(
            UserRequestType::OrganizationInvite,
            $organization->id,
            'orgmember',
            $owner,
        );

        $this->assertSame([], $results);
        $this->assertTrue(
            collect($results)->doesntContain(fn (array $row): bool => $row['id'] === $member->id)
        );
    }
}
