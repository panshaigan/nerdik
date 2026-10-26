<?php

namespace Tests\Unit\Services;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Services\ActivityParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActivityParticipationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivityParticipationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notification_throttle.participation_mutations_per_minute' => 30]);
        $this->service = app(ActivityParticipationService::class);
    }

    #[Test]
    public function join_succeeds_for_joinable_open_activity(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('You joined the activity.'), $response->getSession()->get('status'));
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $joiner->id)
                ->exists()
        );
    }

    #[Test]
    public function join_blocked_when_activity_cancelled(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, ['cancelled_at' => now()]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('ui.activities.signup_blocked_cancelled'), $response->getSession()->get('status'));
        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists());
    }

    #[Test]
    public function join_blocked_when_hosting_mode_not_joinable(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'participation_mode' => ParticipationMode::Open,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('ui.activities.signup_blocked_not_joinable_mode'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_blocked_when_already_participating(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'is_absent' => false,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('You are already participating.'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_blocked_when_already_on_waitlist(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'position' => 1,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(
            __('You are on the waitlist. Leave it first if you want to join directly.'),
            $response->getSession()->get('status'),
        );
    }

    #[Test]
    public function join_blocked_when_host_approval_mode(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('ui.activities.join_requires_waitlist'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_blocked_when_lottery_mode(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 24,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('ui.activities.join_requires_waitlist'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_blocked_when_activity_full(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, ['max_participants' => 1]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $response = $this->service->join($activity, $joiner);

        $this->assertSame(__('Activity is full. You can join the waitlist.'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_waitlist_succeeds_when_approval_required(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);

        $response = $this->service->joinWaitlist($activity, $joiner);

        $this->assertSame(__('You joined the waitlist.'), $response->getSession()->get('status'));
        $this->assertTrue(
            ActivityWaitlistEntry::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $joiner->id)
                ->exists()
        );
    }

    #[Test]
    public function join_waitlist_succeeds_when_open_activity_is_full(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, ['max_participants' => 1]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $response = $this->service->joinWaitlist($activity, $joiner);

        $this->assertSame(__('You joined the waitlist.'), $response->getSession()->get('status'));
    }

    #[Test]
    public function join_waitlist_blocked_for_open_activity_with_capacity(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, ['max_participants' => 5]);

        $response = $this->service->joinWaitlist($activity, $joiner);

        $this->assertSame(
            __('ui.activities.waitlist_only_when_approval_or_full'),
            $response->getSession()->get('status'),
        );
    }

    #[Test]
    public function leave_removes_participant(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'is_absent' => false,
        ]);

        $response = $this->service->leave($activity, $joiner);

        $this->assertSame(__('You left the activity.'), $response->getSession()->get('status'));
        $this->assertFalse(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $joiner->id)
                ->exists()
        );
    }

    #[Test]
    public function approve_waitlist_entry_promotes_user_when_host_and_capacity_available(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::HostApproval,
            'max_participants' => 3,
        ]);
        $entry = ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'position' => 1,
        ]);

        $response = $this->service->approveWaitlistEntry($activity, $entry, $host);

        $this->assertSame(__('ui.activities.waitlist_entry_approved'), $response->getSession()->get('status'));
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $joiner->id)
                ->exists()
        );
        $this->assertFalse(ActivityWaitlistEntry::query()->whereKey($entry->id)->exists());
    }

    #[Test]
    public function approve_waitlist_entry_forbidden_for_non_host(): void
    {
        $host = User::factory()->create();
        $stranger = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);
        $entry = ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'position' => 1,
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(__('ui.activities.only_host_can_approve_waitlist'));

        $this->service->approveWaitlistEntry($activity, $entry, $stranger);
    }

    #[Test]
    public function approve_waitlist_entry_blocked_when_activity_full(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->joinableSelfHosted($host, [
            'participation_mode' => ParticipationMode::HostApproval,
            'max_participants' => 1,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);
        $entry = ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'position' => 1,
        ]);

        $response = $this->service->approveWaitlistEntry($activity, $entry, $host);

        $this->assertSame(__('Activity is full.'), $response->getSession()->get('status'));
        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function joinableSelfHosted(User $host, array $overrides = []): Activity
    {
        return Activity::factory()->create(array_merge([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
        ], $overrides));
    }
}
