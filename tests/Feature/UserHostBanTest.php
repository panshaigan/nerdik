<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\UserContactPopover;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Models\UserHostBan;
use App\Services\ActivityParticipationService;
use App\Services\UserHostBanService;
use App\Support\Users\UserReputationStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserHostBanTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_ban_and_unban_from_contact_popover(): void
    {
        $host = User::factory()->create();
        $target = User::factory()->create();
        Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'participation_mode' => ParticipationMode::Open,
        ]);

        Livewire::actingAs($host)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertSee(__('ui.profile.ban_user'))
            ->call('banUser')
            ->assertSee(__('ui.profile.unban_user'))
            ->assertDontSee(__('ui.profile.ban_user'));

        $this->assertTrue(
            UserHostBan::query()
                ->where('host_user_id', $host->id)
                ->where('banned_user_id', $target->id)
                ->exists()
        );

        Livewire::actingAs($host)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->call('unbanUser')
            ->assertSee(__('ui.profile.ban_user'));

        $this->assertFalse(
            UserHostBan::query()
                ->where('host_user_id', $host->id)
                ->where('banned_user_id', $target->id)
                ->exists()
        );
    }

    public function test_non_host_does_not_see_ban_button(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertDontSee(__('ui.profile.ban_user'))
            ->assertDontSee(__('ui.profile.unban_user'));
    }

    public function test_host_cannot_ban_self(): void
    {
        $host = User::factory()->create();
        Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
        ]);

        Livewire::actingAs($host)
            ->test(UserContactPopover::class, ['targetUserId' => $host->id])
            ->assertDontSee(__('ui.profile.ban_user'));

        $this->expectException(ValidationException::class);
        app(UserHostBanService::class)->ban($host, $host);
    }

    public function test_ban_blocks_join_on_host_activity_but_not_other_hosts(): void
    {
        $host = User::factory()->create();
        $otherHost = User::factory()->create();
        $banned = User::factory()->create();

        $hostActivity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 10,
        ]);
        $otherActivity = Activity::factory()->create([
            'created_by' => $otherHost->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 10,
        ]);

        app(UserHostBanService::class)->ban($host, $banned);

        $participation = app(ActivityParticipationService::class);
        $blocked = $participation->join($hostActivity, $banned);
        $this->assertSame(
            __('ui.activities.signup_blocked_banned_by_host'),
            $blocked->getSession()->get('status')
        );
        $this->assertFalse(
            ActivityUser::query()
                ->where('activity_id', $hostActivity->id)
                ->where('user_id', $banned->id)
                ->exists()
        );

        $participation->join($otherActivity, $banned);
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $otherActivity->id)
                ->where('user_id', $banned->id)
                ->exists()
        );
    }

    public function test_ban_removes_upcoming_participation_and_waitlist_but_keeps_past(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $target = User::factory()->create();

        $upcoming = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 10,
        ]);
        $upcomingWaitlist = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(3),
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 10,
        ]);
        $past = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(3),
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 10,
        ]);

        $upcomingParticipant = ActivityUser::query()->create([
            'activity_id' => $upcoming->id,
            'user_id' => $target->id,
            'is_absent' => false,
        ]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $upcomingWaitlist->id,
            'user_id' => $target->id,
            'position' => 1,
        ]);
        $pastParticipant = ActivityUser::query()->create([
            'activity_id' => $past->id,
            'user_id' => $target->id,
            'is_absent' => true,
        ]);

        app(UserHostBanService::class)->ban($host, $target);

        $this->assertDatabaseMissing('activity_user', [
            'id' => $upcomingParticipant->id,
        ]);
        $this->assertDatabaseMissing('activity_waitlist_entries', [
            'activity_id' => $upcomingWaitlist->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseHas('activity_user', [
            'id' => $pastParticipant->id,
            'is_absent' => true,
        ]);
    }

    public function test_reputation_warning_thresholds(): void
    {
        $user = User::factory()->create();
        $stats = app(UserReputationStats::class);

        $this->assertFalse($stats->shouldShowWarning($user));

        $hostA = User::factory()->create();
        UserHostBan::factory()->create([
            'host_user_id' => $hostA->id,
            'banned_user_id' => $user->id,
        ]);
        $this->assertFalse($stats->shouldShowWarning($user));

        $hostB = User::factory()->create();
        UserHostBan::factory()->create([
            'host_user_id' => $hostB->id,
            'banned_user_id' => $user->id,
        ]);
        $this->assertTrue($stats->shouldShowWarning($user));

        $other = User::factory()->create();
        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $other->id,
            'is_absent' => true,
        ]);
        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $other->id,
            'is_absent' => true,
        ]);
        $this->assertFalse($stats->shouldShowWarning($other));

        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $other->id,
            'is_absent' => true,
        ]);
        $this->assertTrue($stats->shouldShowWarning($other));
    }

    public function test_contact_popover_shows_ban_and_absence_counts(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();

        UserHostBan::factory()->create([
            'host_user_id' => $hostA->id,
            'banned_user_id' => $target->id,
        ]);
        UserHostBan::factory()->create([
            'host_user_id' => $hostB->id,
            'banned_user_id' => $target->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $target->id,
            'is_absent' => true,
        ]);
        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $target->id,
            'is_absent' => true,
        ]);
        ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create()->id,
            'user_id' => $target->id,
            'is_absent' => true,
        ]);

        Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertSeeHtml('data-ui="user-contact-popover-host-ban-count">2')
            ->assertSeeHtml('data-ui="user-contact-popover-absence-count">3');
    }

    public function test_user_badge_shows_reputation_warning_when_thresholds_met(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        UserHostBan::factory()->create([
            'host_user_id' => User::factory()->create()->id,
            'banned_user_id' => $target->id,
        ]);
        UserHostBan::factory()->create([
            'host_user_id' => User::factory()->create()->id,
            'banned_user_id' => $target->id,
        ]);

        $this->actingAs($viewer);

        $html = Blade::render(
            '<x-user-badge :user="$user" :contact-popover="false" />',
            ['user' => $target],
        );

        $this->assertStringContainsString('data-ui="user-badge-reputation-warning"', $html);
    }
}
