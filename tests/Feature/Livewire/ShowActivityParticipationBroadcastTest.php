<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowActivityParticipationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatched_browser_event_triggers_broadcast_handler(): void
    {
        $viewer = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        Livewire::actingAs($viewer)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->call('__dispatch', 'activity-participation-updated', ['activityId' => $activity->id])
            ->assertSet('participationBroadcastRefreshTick', 1);
    }

    public function test_broadcast_handler_bumps_refresh_tick_when_activity_and_tab_match(): void
    {
        $viewer = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $component = Livewire::actingAs($viewer)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation');

        $component->assertSet('participationBroadcastRefreshTick', 0);

        $component->call('refreshParticipationFromBroadcast', $activity->id);

        $component->assertSet('participationBroadcastRefreshTick', 1);
    }

    public function test_broadcast_handler_ignored_when_activity_id_does_not_match(): void
    {
        $viewer = User::factory()->create();
        $mountedActivity = Activity::factory()->create([
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $otherActivity = Activity::factory()->create([
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $component = Livewire::actingAs($viewer)
            ->test(ShowActivity::class, ['activity' => $mountedActivity])
            ->set('tab', 'participation');

        $component->call('refreshParticipationFromBroadcast', $otherActivity->id);

        $component->assertSet('participationBroadcastRefreshTick', 0);
    }

    public function test_broadcast_handler_ignored_when_tab_is_not_participation(): void
    {
        $viewer = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $viewer->id,
            'updated_by' => $viewer->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $component = Livewire::actingAs($viewer)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'info');

        $component->call('refreshParticipationFromBroadcast', $activity->id);

        $component->assertSet('participationBroadcastRefreshTick', 0);
    }
}
