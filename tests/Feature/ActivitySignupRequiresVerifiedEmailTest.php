<?php

namespace Tests\Feature;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Services\ActivityParticipationService;
use App\Services\ActivityParticipationViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivitySignupRequiresVerifiedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_join_via_http(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->unverified()->create();
        $activity = $this->openSelfHostedActivity($host);

        $this->actingAs($joiner)
            ->post(route('activities.join', $activity))
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists()
        );
    }

    public function test_unverified_user_cannot_join_waitlist_via_http(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->unverified()->create();
        $activity = $this->openSelfHostedActivity($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);

        $this->actingAs($joiner)
            ->post(route('activities.join-waitlist', $activity))
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse(
            ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists()
        );
    }

    public function test_unverified_user_cannot_join_via_livewire(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->unverified()->create();
        $activity = $this->openSelfHostedActivity($host);

        Livewire::actingAs($joiner)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join');

        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists()
        );
    }

    public function test_verified_user_can_join_via_livewire(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->create();
        $activity = $this->openSelfHostedActivity($host);

        Livewire::actingAs($joiner)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join');

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists()
        );
    }

    public function test_unverified_user_sees_signup_blocked_and_cannot_join_in_view_state(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->unverified()->create();
        $activity = $this->openSelfHostedActivity($host);

        $vm = app(ActivityParticipationViewService::class)->forShow($activity, $joiner);

        $this->assertFalse($vm->canJoin);
        $this->assertSame(
            __('ui.activities.signup_blocked_unverified_email'),
            $vm->signupBlockedMessage
        );
    }

    public function test_host_cannot_approve_unverified_waitlist_entry(): void
    {
        $host = User::factory()->create();
        $joiner = User::factory()->unverified()->create();
        $activity = $this->openSelfHostedActivity($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);

        $entry = ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $joiner->id,
            'position' => 1,
        ]);

        $response = app(ActivityParticipationService::class)
            ->approveWaitlistEntry($activity, $entry, $host);

        $this->assertSame(
            __('ui.activities.signup_blocked_unverified_email_participant'),
            $response->getSession()->get('status')
        );
        $this->assertFalse(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $joiner->id)->exists()
        );
        $this->assertTrue(
            ActivityWaitlistEntry::query()->whereKey($entry->id)->exists()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openSelfHostedActivity(User $host, array $overrides = []): Activity
    {
        return Activity::factory()->create(array_merge([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'starts_at' => now()->addDay(),
        ], $overrides));
    }
}
