<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_routes_redirect_guests(): void
    {
        $this->get(route('me.events'))->assertRedirect();
        $this->get(route('me.activities'))->assertRedirect();
        $this->get(route('me.participated-activities'))->assertRedirect();
    }

    public function test_my_events_page_lists_owned_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'UniqueOwnedConEvent',
        ]);

        $this->actingAs($user)
            ->get(route('me.events'))
            ->assertOk()
            ->assertSee('UniqueOwnedConEvent', false);
    }

    public function test_my_activities_page_lists_owned_activity(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'name' => 'UniqueOwnedActivityName',
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->actingAs($user)
            ->get(route('me.activities'))
            ->assertOk()
            ->assertSee('UniqueOwnedActivityName', false);
    }

    public function test_participated_activities_page_lists_joined_activity(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'name' => 'UniqueJoinedActivityName',
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        $this->actingAs($participant)
            ->get(route('me.participated-activities'))
            ->assertOk()
            ->assertSee('UniqueJoinedActivityName', false);
    }
}
