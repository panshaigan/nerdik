<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\EventActivitySignupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentWindowQuotasTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_per_activity_window_cap_blocks_after_limit_is_taken(): void
    {
        [$event, $activity] = $this->createEventActivityPair();
        $now = now()->startOfMinute();
        $this->createWindow($event, $now->copy()->subHour(), $now->copy()->addHour(), maxPerActivity: 2);

        Carbon::setTestNow($now);

        $takenA = User::factory()->create();
        $takenB = User::factory()->create();
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $takenA->id, 'created_at' => $now->copy()->subMinutes(20), 'updated_at' => $now]);
        ActivityUser::query()->create(['activity_id' => $activity->id, 'user_id' => $takenB->id, 'created_at' => $now->copy()->subMinutes(10), 'updated_at' => $now]);

        $candidate = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(EventActivitySignupService::class)->assertCanSignup($activity, $candidate);
    }

    public function test_waitlist_entries_do_not_consume_per_activity_window_cap(): void
    {
        [$event, $activity] = $this->createEventActivityPair();
        $now = now()->startOfMinute();
        $this->createWindow($event, $now->copy()->subHour(), $now->copy()->addHour(), maxPerActivity: 2);
        Carbon::setTestNow($now);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => $now->copy()->subMinutes(15),
            'updated_at' => $now,
        ]);
        ActivityWaitlistEntry::factory()->count(5)->create(['activity_id' => $activity->id]);

        $candidate = User::factory()->create();
        app(EventActivitySignupService::class)->assertCanSignup($activity, $candidate);
        $this->assertTrue(true);
    }

    public function test_accumulative_windows_carry_unused_quota_forward(): void
    {
        [$event, $activity] = $this->createEventActivityPair();
        $otherActivity = $this->createActivityOnEvent($event);
        $user = User::factory()->create();

        $w1Start = now()->subDays(3)->startOfMinute();
        $w1End = now()->subDays(2)->startOfMinute();
        $w2Start = now()->subHour()->startOfMinute();
        $w2End = now()->addHour()->startOfMinute();
        $this->createWindow($event, $w1Start, $w1End, maxPerUser: 1, accumulative: true);
        $this->createWindow($event, $w2Start, $w2End, maxPerUser: 1, accumulative: true);

        Carbon::setTestNow($w2Start->copy()->addMinutes(10));

        ActivityUser::query()->create([
            'activity_id' => $otherActivity->id,
            'user_id' => $user->id,
            'created_at' => $w2Start->copy()->addMinutes(1),
            'updated_at' => $w2Start->copy()->addMinutes(1),
        ]);

        app(EventActivitySignupService::class)->assertCanSignup($activity, $user);
        $this->assertTrue(true);
    }

    public function test_non_accumulative_current_window_does_not_add_past_unused_quota(): void
    {
        [$event, $activity] = $this->createEventActivityPair();
        $otherActivity = $this->createActivityOnEvent($event);
        $user = User::factory()->create();

        $w1Start = now()->subDays(3)->startOfMinute();
        $w1End = now()->subDays(2)->startOfMinute();
        $w2Start = now()->subHour()->startOfMinute();
        $w2End = now()->addHour()->startOfMinute();
        $this->createWindow($event, $w1Start, $w1End, maxPerUser: 1, accumulative: true);
        $this->createWindow($event, $w2Start, $w2End, maxPerUser: 1, accumulative: false);

        Carbon::setTestNow($w2Start->copy()->addMinutes(10));

        ActivityUser::query()->create([
            'activity_id' => $otherActivity->id,
            'user_id' => $user->id,
            'created_at' => $w2Start->copy()->addMinutes(1),
            'updated_at' => $w2Start->copy()->addMinutes(1),
        ]);

        $this->expectException(ValidationException::class);
        app(EventActivitySignupService::class)->assertCanSignup($activity, $user);
    }

    private function createEventActivityPair(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'organization_id' => null,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
        ]);
        $activity = $this->createActivityOnEvent($event);

        return [$event, $activity];
    }

    private function createActivityOnEvent(Event $event): Activity
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $event->created_by,
            'updated_by' => $event->updated_by,
            'starts_at' => null,
            'ends_at' => null,
            'place_id' => null,
        ]);

        return $activity;
    }

    private function createWindow(
        Event $event,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $maxPerUser = null,
        ?int $maxPerActivity = null,
        bool $accumulative = false
    ): void {
        $event->enrollmentWindows()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'max_activities_per_user' => $maxPerUser,
            'max_allowed_participants_per_activity' => $maxPerActivity,
            'accumulative_activities' => $accumulative,
        ]);
    }
}
