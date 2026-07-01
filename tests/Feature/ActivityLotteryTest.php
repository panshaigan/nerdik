<?php

namespace Tests\Feature;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityLotteryDraw;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use App\Services\ActivityLotteryService;
use App\Services\ActivityParticipantRosterService;
use App\Services\ActivityParticipationService;
use App\Services\EventActivitySignupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivityLotteryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lottery_mode_requires_waitlist_signup(): void
    {
        $host = User::factory()->create();
        $user = User::factory()->create();
        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 4);

        $this->actingAs($user);
        app(ActivityParticipationService::class)->join($activity, $user);

        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $user->id)->exists());
        $this->assertFalse(ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $user->id)->exists());

        app(ActivityParticipationService::class)->joinWaitlist($activity, $user);

        $this->assertTrue(ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $user->id)->exists());
    }

    public function test_resolve_command_promotes_random_waitlist_subset_and_notifies_winners_only(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 2);
        $drawAt = app(ActivityLotteryService::class)->lotteryDrawAt($activity);
        $this->assertNotNull($drawAt);

        $waitlistUsers = User::factory()->count(4)->create();
        foreach ($waitlistUsers as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        Carbon::setTestNow($drawAt->copy()->addMinute());

        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $activity->refresh();
        $this->assertNotNull($activity->lottery_resolved_at);
        $this->assertSame(2, $activity->participants()->count());

        Notification::assertSentTimes(WaitlistPromotedNotification::class, 2);

        foreach ($waitlistUsers as $waitlistUser) {
            $promoted = ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $waitlistUser->id)
                ->exists();
            if ($promoted) {
                Notification::assertSentTo($waitlistUser, WaitlistPromotedNotification::class);
            } else {
                Notification::assertNotSentTo($waitlistUser, WaitlistPromotedNotification::class);
            }
        }
    }

    public function test_self_hosted_does_not_resolve_at_cancellation_deadline_when_lottery_draw_is_later(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $startsAt = now()->addDays(3);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Lottery,
            'cancellation_deadline_in_hours' => 48,
            'lottery_draw_in_hours' => 12,
            'max_participants' => 2,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
        ]);

        $user = User::factory()->create();
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'position' => 1,
        ]);

        Carbon::setTestNow($activity->cancellationDeadlineAt()->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $activity->refresh();
        $this->assertNull($activity->lottery_resolved_at);
        $this->assertSame(0, $activity->participants()->count());
        Notification::assertNothingSent();

        Carbon::setTestNow($activity->lotteryDrawAt()->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $activity->refresh();
        $this->assertNotNull($activity->lottery_resolved_at);
        $this->assertSame(1, $activity->participants()->count());
    }

    public function test_resolve_command_is_idempotent(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 1);
        $user = User::factory()->create();
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'position' => 1,
        ]);

        Carbon::setTestNow(app(ActivityLotteryService::class)->lotteryDrawAt($activity)->copy()->addMinute());

        $this->artisan('activities:resolve-lotteries')->assertSuccessful();
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $this->assertSame(1, ActivityUser::query()->where('activity_id', $activity->id)->count());
        Notification::assertSentToTimes($user, WaitlistPromotedNotification::class, 1);
        $this->assertSame(1, ActivityLotteryDraw::query()->where('activity_id', $activity->id)->count());
    }

    public function test_leave_after_lottery_resolved_promotes_random_waitlist_entry(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $dave = User::factory()->create();

        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 2);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);
        foreach ([$carol, $dave] as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        $activity->update(['lottery_resolved_at' => now()]);

        $bobRow = ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $bob->id)->firstOrFail();
        app(EventActivitySignupService::class)->userLeaveActivity($activity->fresh(), $bobRow);

        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $bob->id)->exists());
        $this->assertSame(2, ActivityUser::query()->where('activity_id', $activity->id)->count());

        $promotedCount = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->whereIn('user_id', [$carol->id, $dave->id])
            ->count();
        $this->assertSame(1, $promotedCount);

        Notification::assertSentTimes(WaitlistPromotedNotification::class, 1);
    }

    public function test_lottery_pending_leave_does_not_promote_waitlist(): void
    {
        $host = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();

        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 2);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $carol->id,
            'position' => 1,
        ]);

        $this->actingAs($bob);
        app(ActivityParticipationService::class)->leave($activity->fresh(), $bob);

        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $carol->id)->exists());
    }

    public function test_event_enrollment_window_end_triggers_intermediate_draw_without_resolving(): void
    {
        Notification::fake();

        [$event, $activity] = $this->createEventLotteryActivity(maxParticipants: 4, lotteryDrawInHours: 48);
        $windowEnd = now()->addHour();
        $event->enrollmentWindows()->create([
            'name' => 'Signup',
            'starts_at' => now()->subHour(),
            'ends_at' => $windowEnd,
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => 2,
            'accumulative_activities' => false,
        ]);

        $waitlistUsers = User::factory()->count(3)->create();
        foreach ($waitlistUsers as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        Carbon::setTestNow($windowEnd->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $activity->refresh();
        $this->assertNull($activity->lottery_resolved_at);
        $this->assertSame(2, $activity->participants()->count());
        Notification::assertSentTimes(WaitlistPromotedNotification::class, 2);
    }

    public function test_event_two_windows_draw_respects_per_window_caps(): void
    {
        Notification::fake();

        [$event, $activity] = $this->createEventLotteryActivity(maxParticipants: 6, lotteryDrawInHours: 72);
        $windowOneEnd = now()->addHour();
        $windowTwoEnd = now()->addHours(3);

        $event->enrollmentWindows()->create([
            'name' => 'Window A',
            'starts_at' => now()->subHour(),
            'ends_at' => $windowOneEnd,
            'max_allowed_participants_per_activity' => 1,
            'accumulative_activities' => false,
        ]);
        $event->enrollmentWindows()->create([
            'name' => 'Window B',
            'starts_at' => $windowOneEnd->copy()->addHour(),
            'ends_at' => $windowTwoEnd,
            'max_allowed_participants_per_activity' => 2,
            'accumulative_activities' => false,
        ]);

        $waitlistUsers = User::factory()->count(5)->create();
        foreach ($waitlistUsers as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        Carbon::setTestNow($windowOneEnd->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();
        $this->assertSame(1, ActivityUser::query()->where('activity_id', $activity->id)->count());

        Carbon::setTestNow($windowTwoEnd->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();
        $this->assertSame(3, ActivityUser::query()->where('activity_id', $activity->id)->count());
        $this->assertNull($activity->fresh()->lottery_resolved_at);
    }

    public function test_final_draw_fills_remaining_spots_and_resolves_lottery(): void
    {
        Notification::fake();

        [$event, $activity] = $this->createEventLotteryActivity(maxParticipants: 4, lotteryDrawInHours: 24);
        $windowEnd = now()->addDays(2);
        $event->enrollmentWindows()->create([
            'name' => 'Late window',
            'starts_at' => now()->subHour(),
            'ends_at' => $windowEnd,
            'max_allowed_participants_per_activity' => 1,
            'accumulative_activities' => false,
        ]);

        $waitlistUsers = User::factory()->count(4)->create();
        foreach ($waitlistUsers as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        $finalDrawAt = app(ActivityLotteryService::class)->lotteryDrawAt($activity->fresh('slot'));
        $this->assertNotNull($finalDrawAt);

        Carbon::setTestNow($finalDrawAt->copy()->addMinute());
        $this->artisan('activities:resolve-lotteries')->assertSuccessful();

        $activity->refresh();
        $this->assertNotNull($activity->lottery_resolved_at);
        $this->assertSame(4, $activity->participants()->count());
    }

    public function test_host_remove_after_lottery_resolved_promotes_random_waitlist_entry(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $dave = User::factory()->create();

        $activity = $this->createSelfHostedLotteryActivity($host, maxParticipants: 2);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $alice->id]);
        $bobRow = ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $bob->id]);
        foreach ([$carol, $dave] as $index => $waitlistUser) {
            ActivityWaitlistEntry::query()->create([
                'activity_id' => $activity->id,
                'user_id' => $waitlistUser->id,
                'position' => $index + 1,
            ]);
        }

        $activity->update(['lottery_resolved_at' => now()]);

        app(ActivityParticipantRosterService::class)->removeParticipant($bobRow);

        $this->assertFalse(ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $bob->id)->exists());
        $this->assertSame(2, ActivityUser::query()->where('activity_id', $activity->id)->count());

        $promotedCount = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->whereIn('user_id', [$carol->id, $dave->id])
            ->count();
        $this->assertSame(1, $promotedCount);

        Notification::assertSentTimes(WaitlistPromotedNotification::class, 1);
    }

    private function createSelfHostedLotteryActivity(User $host, ?int $maxParticipants = 4): Activity
    {
        return Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 24,
            'cancellation_deadline_in_hours' => 24,
            'max_participants' => $maxParticipants,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(3),
        ]);
    }

    /**
     * @return array{0: Event, 1: Activity}
     */
    private function createEventLotteryActivity(?int $maxParticipants = 4, int $lotteryDrawInHours = 12): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'organization_id' => null,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
        ]);
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'participation_mode' => ParticipationMode::Lottery,
            'max_participants' => $maxParticipants,
            'lottery_draw_in_hours' => $lotteryDrawInHours,
            'cancellation_deadline_in_hours' => 12,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $event->created_by,
            'updated_by' => $event->updated_by,
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'ends_at' => now()->addDays(5)->setTime(13, 0),
            'place_id' => null,
        ]);

        return [$event, $activity];
    }
}
