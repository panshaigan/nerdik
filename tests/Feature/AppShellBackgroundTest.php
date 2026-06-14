<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SeedsListingDefaultMedia;
use Tests\TestCase;

final class AppShellBackgroundTest extends TestCase
{
    use RefreshDatabase;
    use SeedsListingDefaultMedia;

    #[Test]
    public function app_layout_renders_full_page_background_on_standard_pages(): void
    {
        $response = $this->get(route('privacy'));

        $response->assertOk();
        $response->assertSee('data-ui="app-shell-background"', false);
        $response->assertSee('images/app/background.webp', false);
        $response->assertSee('images/app/background-light.webp', false);
        $response->assertSee('fixed inset-0 z-0', false);
        $response->assertSee('relative z-10 flex min-h-screen flex-col', false);
        $response->assertSee('<footer class="border-t border-white/10 backdrop-blur-xs">', false);
        $response->assertDontSee('bg-base-100/90', false);
    }

    #[Test]
    public function welcome_page_renders_full_page_background(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-ui="app-shell-background"', false);
        $response->assertSee('images/app/background.webp', false);
        $response->assertSee('images/app/background-light.webp', false);
    }

    #[Test]
    public function guest_auth_layout_renders_full_page_background(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('data-ui="app-shell-background"', false);
        $response->assertSee('images/app/background.webp', false);
        $response->assertSee('images/app/background-light.webp', false);
    }

    #[Test]
    public function activity_show_page_excludes_app_shell_background(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(7)->setSecond(0);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
        ]);

        $response = $this->get(route('activities.show', $activity));

        $response->assertOk();
        $response->assertSee('data-ui="activity-show-page-background"', false);
        $response->assertSee('fixed inset-0 z-0', false);
        $response->assertDontSee('data-ui="app-shell-background"', false);
    }

    #[Test]
    public function event_show_page_excludes_app_shell_background(): void
    {
        $this->seedListingDefaultMedia();

        $event = Event::factory()->create([
            'is_public' => true,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('data-ui="event-show-page-background"', false);
        $response->assertSee('fixed inset-0 z-0', false);
        $response->assertDontSee('data-ui="app-shell-background"', false);
    }
}
