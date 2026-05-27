<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_uses_invitation_first_content_without_technical_footer_details(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Find your next unforgettable session.', false);
        $response->assertSee('Closest activities &amp; events', false);
        $response->assertDontSee('Laravel v', false);
        $response->assertDontSee('PHP v', false);
    }

    public function test_home_page_shows_nearest_upcoming_public_listings_only(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();

        $upcomingEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Aurora Convention',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);
        $upcomingEvent->places()->sync([$place->id]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'name' => 'Starlight One-Shot',
        ]);

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Past Gathering',
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subDays(6),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Aurora Convention', false);
        $response->assertSee('Starlight One-Shot', false);
        $response->assertDontSee('Past Gathering', false);
        $response->assertSee(route('search.index'), false);
        $response->assertSee(route('events.show', $upcomingEvent), false);
    }
}
