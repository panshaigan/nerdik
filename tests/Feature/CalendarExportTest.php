<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CalendarExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_download_event_ics(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addWeek()->setTime(10, 0)->utc();
        $endsAt = $startsAt->copy()->addDays(2)->setTime(18, 0);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Public Festival',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->get(route('events.calendar.ics', $event));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('attachment; filename="', (string) $response->headers->get('content-disposition'));
        $response->assertSee('BEGIN:VEVENT', false);
        $response->assertSee('SUMMARY:Public Festival', false);
        $response->assertSee('DTSTART:'.$startsAt->format('Ymd\THis\Z'), false);
        $response->assertSee('DTEND:'.$endsAt->format('Ymd\THis\Z'), false);
        $this->assertSame(1, substr_count($response->getContent(), 'BEGIN:VEVENT'));
    }

    public function test_cancelled_event_ics_returns_not_found(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->get(route('events.calendar.ics', $event))->assertNotFound();
    }

    public function test_guest_can_download_self_hosted_activity_ics(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(4)->setTime(19, 0)->utc();
        $endsAt = $startsAt->copy()->addHours(3);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Open Table',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->get(route('activities.calendar.ics', $activity));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $response->assertSee('SUMMARY:Open Table', false);
        $response->assertSee('DTSTART:'.$startsAt->format('Ymd\THis\Z'), false);
        $response->assertSee('DTEND:'.$endsAt->format('Ymd\THis\Z'), false);
    }

    public function test_draft_activity_ics_returns_not_found(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);

        $this->get(route('activities.calendar.ics', $activity))->assertNotFound();
    }
}
