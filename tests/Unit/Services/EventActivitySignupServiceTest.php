<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ParticipationMode;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventActivitySignupServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventActivitySignupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventActivitySignupService::class);
    }

    #[Test]
    public function user_leave_waitlist_reorders_remaining_positions(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::HostApproval,
        ]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $entry1 = ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $first->id,
            'position' => 1,
        ]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $second->id,
            'position' => 2,
        ]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $third->id,
            'position' => 3,
        ]);

        $this->service->userLeaveWaitlist($activity, $entry1);

        $this->assertFalse(ActivityWaitlistEntry::query()->whereKey($entry1->id)->exists());
        $this->assertSame(1, (int) ActivityWaitlistEntry::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $second->id)
            ->value('position'));
        $this->assertSame(2, (int) ActivityWaitlistEntry::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $third->id)
            ->value('position'));
    }

    #[Test]
    public function activity_scheduled_window_for_self_hosted_uses_starts_and_ends(): void
    {
        $starts = now()->addDay()->startOfHour();
        $ends = $starts->copy()->addHours(2);
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);

        $window = $this->service->activityScheduledWindow($activity);

        $this->assertNotNull($window);
        $this->assertTrue($starts->equalTo($window[0]));
        $this->assertTrue($ends->equalTo($window[1]));
    }

    #[Test]
    public function activity_scheduled_window_returns_null_when_self_hosted_has_no_start(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertNull($this->service->activityScheduledWindow($activity));
    }

    #[Test]
    public function activity_scheduled_window_for_scheduled_activity_uses_slot_and_duration(): void
    {
        $event = Event::factory()->create();
        $slotStart = now()->addDays(2)->startOfHour();
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'duration_in_minutes' => 90,
            'starts_at' => null,
            'ends_at' => null,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => $slotStart,
            'ends_at' => $slotStart->copy()->addHours(3),
        ]);
        $activity->load('slot');

        $window = $this->service->activityScheduledWindow($activity->fresh()->load('slot'));

        $this->assertNotNull($window);
        $this->assertTrue($slotStart->equalTo($window[0]));
        $this->assertTrue($slotStart->copy()->addMinutes(90)->equalTo($window[1]));
    }

    #[Test]
    public function schedules_overlap_detects_overlapping_self_hosted_windows(): void
    {
        $start = now()->addDay()->startOfHour();
        $a = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
        ]);
        $b = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $start->copy()->addHour(),
            'ends_at' => $start->copy()->addHours(3),
        ]);
        $c = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $start->copy()->addHours(3),
            'ends_at' => $start->copy()->addHours(4),
        ]);

        $this->assertTrue($this->service->schedulesOverlap($a, $b));
        $this->assertFalse($this->service->schedulesOverlap($a, $c));
    }

    #[Test]
    public function format_overlapping_activity_labels_includes_event_name_when_present(): void
    {
        $event = Event::factory()->create(['name' => 'Con Event']);
        $scheduled = Activity::factory()->create([
            'name' => 'Panel A',
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $scheduled->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);
        $selfHosted = Activity::factory()->create([
            'name' => 'Meetup',
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $labels = $this->service->formatOverlappingActivityLabels(
            collect([$scheduled->fresh()->load('slot.event'), $selfHosted])
        );

        $this->assertStringContainsString('Panel A (Con Event)', $labels);
        $this->assertStringContainsString('Meetup', $labels);
        $this->assertStringNotContainsString('Meetup (', $labels);
    }

    #[Test]
    public function assert_can_signup_blocks_overlapping_schedule_for_participant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $start = now()->addDay()->startOfHour();

        $existing = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $existing->id,
            'user_id' => $user->id,
            'is_absent' => false,
        ]);

        $target = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $start->copy()->addHour(),
            'ends_at' => $start->copy()->addHours(3),
        ]);

        try {
            // Self-hosted activities skip enrollment windows; lottery resolution still enforces overlap.
            $this->service->assertCanSignup($target, $user, forLotteryResolution: true);
            $this->fail('Expected ValidationException for schedule overlap.');
        } catch (ValidationException $e) {
            $message = $e->errors()['_'][0];
            $this->assertSame(
                __('ui.events.enrollment_schedule_overlap', ['list' => $existing->name]),
                $message,
            );
        }
    }

    #[Test]
    public function validate_and_normalize_period_rows_rejects_incomplete_window(): void
    {
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(7),
        ]);

        try {
            $this->service->validateAndNormalizePeriodRowsForEvent($event, [
                [
                    'starts_at' => now()->addDays(4)->format('Y-m-d\TH:i'),
                    'ends_at' => '',
                ],
            ], Carbon::now('UTC'));
            $this->fail('Expected ValidationException for incomplete window.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment_windows.0', $e->errors());
        }
    }

    #[Test]
    public function validate_and_normalize_period_rows_accepts_valid_window(): void
    {
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(7),
        ]);

        $start = now()->addDays(4)->format('Y-m-d\TH:i');
        $end = now()->addDays(6)->format('Y-m-d\TH:i');

        $normalized = $this->service->validateAndNormalizePeriodRowsForEvent($event, [
            [
                'name' => 'Early bird',
                'starts_at' => $start,
                'ends_at' => $end,
                'max_activities_per_user' => 2,
                'max_allowed_participants_per_activity' => 4,
                'accumulative_activities' => true,
            ],
        ], Carbon::now('UTC'));

        $this->assertCount(1, $normalized);
        $this->assertSame('Early bird', $normalized[0]['name']);
        $this->assertSame(2, $normalized[0]['max_activities_per_user']);
        $this->assertSame(4, $normalized[0]['max_allowed_participants_per_activity']);
        $this->assertTrue($normalized[0]['accumulative_activities']);
    }

    #[Test]
    public function validate_and_normalize_period_rows_rejects_end_after_event_end(): void
    {
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
        ]);

        try {
            $this->service->validateAndNormalizePeriodRowsForEvent($event, [
                [
                    'starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
                    'ends_at' => now()->addDays(8)->format('Y-m-d\TH:i'),
                ],
            ], Carbon::now('UTC'));
            $this->fail('Expected ValidationException when window ends after event.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment_windows.0', $e->errors());
        }
    }
}
