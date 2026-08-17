<?php

namespace Tests\Feature\Broadcast;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EchoPrivateChannelGuestGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_activity_show_page_omits_user_id_used_by_echo_private_subscribe(): void
    {
        $activity = $this->publicActivity();

        $response = $this->get(route('activities.show', $activity));

        $response->assertOk();
        $response->assertSee('data-show-activity-id="'.$activity->id.'"', false);
        $response->assertDontSee('data-user-id', false);
    }

    public function test_authenticated_activity_show_page_exposes_user_id_for_echo_private_subscribe(): void
    {
        $user = User::factory()->create();
        $activity = $this->publicActivity();

        $response = $this->actingAs($user)->get(route('activities.show', $activity));

        $response->assertOk();
        $response->assertSee('data-show-activity-id="'.$activity->id.'"', false);
        $response->assertSee('data-user-id="'.$user->id.'"', false);
    }

    public function test_guest_event_show_page_omits_user_id_used_by_echo_private_subscribe(): void
    {
        $event = Event::factory()->public()->create();

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('data-show-event-id="'.$event->id.'"', false);
        $response->assertDontSee('data-user-id', false);
    }

    public function test_authenticated_event_show_page_exposes_user_id_for_echo_private_subscribe(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();

        $response = $this->actingAs($user)->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('data-show-event-id="'.$event->id.'"', false);
        $response->assertSee('data-user-id="'.$user->id.'"', false);
    }

    public function test_activity_and_event_echo_scripts_skip_private_subscribe_for_guests(): void
    {
        foreach ([
            'resources/js/activities-echo.js',
            'resources/js/events-plan-counters-echo.js',
        ] as $relativePath) {
            $source = file_get_contents(base_path($relativePath));

            $this->assertNotFalse($source);
            $this->assertIsString($source);

            $guestGuardPosition = strpos($source, 'document.body?.dataset?.userId');
            $privateSubscribePosition = strpos($source, 'window.Echo.private');

            $this->assertNotFalse($guestGuardPosition, "{$relativePath} must check data-user-id before subscribing.");
            $this->assertNotFalse($privateSubscribePosition, "{$relativePath} must still subscribe authenticated users to a private channel.");
            $this->assertLessThan(
                $privateSubscribePosition,
                $guestGuardPosition,
                "{$relativePath} must skip Echo.private() when the visitor is a guest.",
            );
        }
    }

    private function publicActivity(): Activity
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(7)->setSecond(0);

        return Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
        ]);
    }
}
