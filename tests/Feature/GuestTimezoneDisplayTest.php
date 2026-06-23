<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTimezoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_event_page_shows_dates_in_browser_timezone(): void
    {
        $startsAt = Carbon::parse('2026-01-15 03:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-01-16 03:00:00', 'UTC');

        $event = Event::factory()->public()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->withCookie('browser_timezone', 'America/New_York')
            ->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('Jan 14-15, 2026', false);
        $response->assertDontSee('Jan 15-16, 2026', false);
    }

    public function test_guest_event_page_without_cookie_uses_warsaw_fallback(): void
    {
        $startsAt = Carbon::parse('2026-01-15 03:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-01-16 03:00:00', 'UTC');

        $event = Event::factory()->public()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('Jan 15-16, 2026', false);
        $response->assertDontSee('Jan 14-15, 2026', false);
    }
}
