<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\ParticipationMode;
use App\Enums\UserRequestStatus;
use App\Jobs\ExpireUserRequestsJob;
use App\Jobs\RecalculateAllTagPopularityJob;
use App\Jobs\ResolveActivityLotteriesJob;
use App\Jobs\SendScheduledPeriodicDigestJob;
use App\Models\Activity;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use App\Notifications\UserRequestResolvedNotification;
use App\Services\ActivityLotteryService;
use App\Services\Notifications\Scheduled\ScheduledPeriodicDigestSender;
use App\Services\TagPopularityRecalculator;
use App\Services\UserRequests\UserRequestExpirer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledMaintenanceJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_resolve_activity_lotteries_job_resolves_due_draws(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 24,
            'cancellation_deadline_in_hours' => 24,
            'max_participants' => 1,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(3),
        ]);
        $user = User::factory()->create();
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'position' => 1,
        ]);

        Carbon::setTestNow(app(ActivityLotteryService::class)->lotteryDrawAt($activity)->copy()->addMinute());

        (new ResolveActivityLotteriesJob)->handle(app(ActivityLotteryService::class));

        $this->assertNotNull($activity->fresh()->lottery_resolved_at);
        $this->assertSame(1, $activity->participants()->count());
    }

    public function test_expire_user_requests_job_expires_due_requests(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->update(['created_by' => $owner->id, 'updated_by' => $owner->id]);
        $recipient = User::factory()->create();

        $request = UserRequest::factory()->organizationInvite()->create([
            'requester_id' => $owner->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'expires_at' => now()->subMinute(),
        ]);

        (new ExpireUserRequestsJob)->handle(app(UserRequestExpirer::class));

        $this->assertSame(UserRequestStatus::Expired, $request->fresh()->status);
        Notification::assertSentTo($owner, UserRequestResolvedNotification::class);
        Notification::assertSentTo($recipient, UserRequestResolvedNotification::class);
    }

    public function test_send_scheduled_periodic_digest_job_sends_for_matching_local_time(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');

        $user = User::factory()->create();
        $user->profile()->update(['timezone' => 'UTC']);
        $organizer = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addDays(2),
        ]);
        $user->interestedEvents()->attach($event->id);

        Notification::fake();

        (new SendScheduledPeriodicDigestJob)->handle(app(ScheduledPeriodicDigestSender::class));

        Notification::assertSentToTimes($user, ScheduledPeriodicDigestNotification::class, 1);
    }

    public function test_recalculate_all_tag_popularity_job_updates_scores_in_bulk(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $popularTag = Tag::factory()->create(['popularity_score' => 0]);
        $quietTag = Tag::factory()->create(['popularity_score' => 9]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activity->tags()->attach($popularTag->id);

        (new RecalculateAllTagPopularityJob)->handle(app(TagPopularityRecalculator::class));

        $this->assertSame(1, $popularTag->fresh()->popularity_score);
        $this->assertSame(0, $quietTag->fresh()->popularity_score);
    }
}
