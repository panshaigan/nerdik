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
}
