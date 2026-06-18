<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestExpirationResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRequestExpirationResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_expiration_is_fourteen_days(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $expiresAt = app(UserRequestExpirationResolver::class)->resolve(
            UserRequestType::OrganizationInvite,
            null,
        );

        $this->assertSame('2026-07-02 12:00:00', $expiresAt->toDateTimeString());
    }

    public function test_activity_invite_expires_at_scheduled_start(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => '2026-06-25 18:00:00',
            'ends_at' => '2026-06-25 22:00:00',
        ]);

        $expiresAt = app(UserRequestExpirationResolver::class)->resolve(
            UserRequestType::ActivityInvite,
            $activity,
        );

        $this->assertSame('2026-06-25 18:00:00', $expiresAt->toDateTimeString());
    }

    public function test_activity_invite_without_start_falls_back_to_fourteen_days(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $expiresAt = app(UserRequestExpirationResolver::class)->resolve(
            UserRequestType::ActivityInvite,
            $activity,
        );

        $this->assertSame('2026-07-02 12:00:00', $expiresAt->toDateTimeString());
    }

    public function test_activity_invite_with_past_start_falls_back_to_fourteen_days(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => '2026-06-10 18:00:00',
            'ends_at' => '2026-06-10 22:00:00',
        ]);

        $expiresAt = app(UserRequestExpirationResolver::class)->resolve(
            UserRequestType::ActivityInvite,
            $activity,
        );

        $this->assertSame('2026-07-02 12:00:00', $expiresAt->toDateTimeString());
    }

    public function test_for_request_uses_loaded_subject(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => '2026-06-20 10:00:00',
            'ends_at' => '2026-06-20 12:00:00',
        ]);

        $request = UserRequest::factory()->activityInvite()->make([
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);
        $request->setRelation('subject', $activity);

        $expiresAt = app(UserRequestExpirationResolver::class)->forRequest($request);

        $this->assertSame('2026-06-20 10:00:00', $expiresAt->toDateTimeString());
    }
}
