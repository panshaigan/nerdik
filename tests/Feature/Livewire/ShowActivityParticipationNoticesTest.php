<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowActivityParticipationNoticesTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_enrollment_window_notices_are_grouped_in_one_tile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'max_allowed_participants_per_activity' => 5,
            'max_activities_per_user' => 2,
        ]);

        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $html = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity->fresh(['slot.event.enrollmentWindows'])])
            ->set('tab', 'participation')
            ->html();

        $this->assertSame(1, substr_count($html, 'data-ui="activity-show-participation-notices"'));
        $this->assertStringContainsString('data-ui="activity-show-window-activity-cap"', $html);
        $this->assertStringContainsString('data-ui="activity-show-window-user-cap"', $html);
        $this->assertStringContainsString(
            __('ui.events.enrollment_window_activity_spots_remaining', [
                'remaining' => 5,
                'max' => 5,
            ]),
            $html
        );
        $this->assertStringContainsString(
            __('ui.events.enrollment_window_user_spots_remaining', ['remaining' => 2]),
            $html
        );
        $this->assertStringContainsString('<ul class="mt-1 list-disc space-y-1 pl-4 text-xs">', $html);

        Carbon::setTestNow();
    }

    public function test_single_blocked_notice_renders_without_list(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $html = Livewire::actingAs($user)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->html();

        $this->assertSame(1, substr_count($html, 'data-ui="activity-show-participation-notices"'));
        $this->assertStringContainsString('data-ui="activity-show-state-blocked"', $html);
        $this->assertStringContainsString(__('ui.activities.signup_blocked_not_joinable_mode'), $html);
        $this->assertStringNotContainsString('<ul class="mt-1 list-disc space-y-1 pl-4 text-xs">', $html);
    }

    public function test_cancellation_deadline_notice_is_visible_to_guests(): void
    {
        $startsAt = Carbon::parse('2026-07-10 18:00:00', 'UTC');
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $startsAt,
            'cancellation_deadline_in_hours' => 24,
        ]);

        $expectedMessage = __('ui.activities.participation_cancellation_deadline_notice', [
            'when' => format_datetime_in_user_tz($activity->cancellationDeadlineAt()),
        ]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-cancellation-deadline"', $html);
        $this->assertStringContainsString($expectedMessage, $html);
    }

    public function test_cancellation_deadline_notice_is_visible_to_authenticated_users(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['timezone' => 'UTC']);
        $startsAt = Carbon::parse('2026-07-10 18:00:00', 'UTC');
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $startsAt,
            'cancellation_deadline_in_hours' => 24,
        ]);

        $this->actingAs($user);
        $expectedMessage = __('ui.activities.participation_cancellation_deadline_notice', [
            'when' => format_datetime_in_user_tz($activity->cancellationDeadlineAt()),
        ]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-cancellation-deadline"', $html);
        $this->assertStringContainsString($expectedMessage, $html);
    }

    public function test_activity_without_cancellation_deadline_hours_omits_notice(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'cancellation_deadline_in_hours' => null,
        ]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->html();

        $this->assertStringNotContainsString('data-ui="activity-show-cancellation-deadline"', $html);
        $this->assertStringNotContainsString('data-ui="activity-show-participation-notices"', $html);
    }
}
