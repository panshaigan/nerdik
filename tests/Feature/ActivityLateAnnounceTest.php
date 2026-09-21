<?php

namespace Tests\Feature;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActivityLateAnnounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_announce_and_clear_late(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member);
        $service = app(ActivityParticipationService::class);

        $service->setParticipantLateMinutes($activity, $member, (int) $member->id, 20);
        $this->assertSame(20, ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->value('late_minutes'));

        $service->setParticipantLateMinutes($activity, $member, (int) $member->id, null);
        $this->assertNull(ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->value('late_minutes'));
    }

    public function test_host_can_announce_late_without_existing_roster_row(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);

        $this->actingAs($host);
        app(ActivityParticipationService::class)
            ->setParticipantLateMinutes($activity, $host, (int) $host->id, 15);

        $row = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $host->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(15, $row->late_minutes);
    }

    public function test_non_participant_cannot_announce_late(): void
    {
        $host = User::factory()->create();
        $outsider = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);

        $this->actingAs($outsider);

        $this->expectException(HttpException::class);
        app(ActivityParticipationService::class)
            ->setParticipantLateMinutes($activity, $outsider, (int) $outsider->id, 10);
    }

    public function test_event_organizer_can_set_late_for_participant_and_host(): void
    {
        $organizer = User::factory()->organizer()->create();
        $host = User::factory()->create();
        $member = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($organizer);
        $service = app(ActivityParticipationService::class);

        $service->setParticipantLateMinutes($activity->fresh(), $organizer, (int) $member->id, 25);
        $this->assertSame(25, ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->value('late_minutes'));

        $service->setParticipantLateMinutes($activity->fresh(), $organizer, (int) $host->id, 30);
        $this->assertSame(30, ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $host->id)
            ->value('late_minutes'));
    }

    public function test_admin_can_set_late_for_anyone(): void
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($admin);
        app(ActivityParticipationService::class)
            ->setParticipantLateMinutes($activity, $admin, (int) $member->id, 12);

        $this->assertSame(12, ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->value('late_minutes'));
    }

    public function test_late_minutes_validation_bounds(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member);

        $this->expectException(ValidationException::class);
        app(ActivityParticipationService::class)
            ->setParticipantLateMinutes($activity, $member, (int) $member->id, 500);
    }

    public function test_late_announce_blocked_after_activity_ends(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member);

        $this->expectException(HttpException::class);
        app(ActivityParticipationService::class)
            ->setParticipantLateMinutes($activity, $member, (int) $member->id, 10);
    }

    public function test_show_activity_livewire_announce_late(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('announceLate', 18)
            ->assertSuccessful();

        $this->assertSame(18, ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->value('late_minutes'));
    }

    public function test_admin_sees_toolbar_and_participant_late_controls(): void
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $admin->id,
        ]);
        $participant = ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
        ]);

        $html = Livewire::actingAs($admin)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-late-announce"', $html);
        $this->assertStringContainsString('data-ui="activity-show-participant-late-'.$participant->id.'"', $html);
        $this->assertStringContainsString('data-ui="activity-show-host-late"', $html);
        $this->assertStringContainsString('this.$wire.announceLate(value)', $html);
        $this->assertStringNotContainsString('\$wire.', $html);
    }

    public function test_user_badge_renders_late_overlay(): void
    {
        $user = User::factory()->create();

        $html = view('components.user-badge', [
            'user' => $user,
            'contactPopover' => false,
            'lateMinutes' => 15,
        ])->render();

        $this->assertStringContainsString('data-ui="user-badge-late"', $html);
        $this->assertStringContainsString('+15', $html);
    }

    public function test_overflow_menu_uses_daisyui_tooltip_not_title(): void
    {
        $html = Blade::render(
            '<x-ui.overflow-menu icon="o-share" label="Share"><span>item</span></x-ui.overflow-menu>'
        );

        $this->assertStringContainsString('data-tip="Share"', $html);
        $this->assertStringContainsString('tooltip tooltip-bottom', $html);
        $this->assertStringNotContainsString('title="Share"', $html);
    }
}
