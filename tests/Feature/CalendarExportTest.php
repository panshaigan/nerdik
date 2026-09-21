<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $this->assertStringContainsString('inline; filename="', (string) $response->headers->get('content-disposition'));
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

    public function test_guest_can_download_event_series_ics_for_upcoming_editions_only(): void
    {
        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'created_by' => $user->id,
            'name' => 'Cycle Con',
        ]);
        $upcomingA = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Cycle One',
            'starts_at' => now()->addDays(7)->setTime(10, 0)->utc(),
            'ends_at' => now()->addDays(7)->setTime(18, 0)->utc(),
        ]);
        $upcomingB = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Cycle Two',
            'starts_at' => now()->addDays(21)->setTime(10, 0)->utc(),
            'ends_at' => now()->addDays(21)->setTime(18, 0)->utc(),
        ]);
        Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Cycle Past',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(4),
        ]);
        Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Cycle Cancelled',
            'starts_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14)->addHours(4),
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $response = $this->get(route('event-series.calendar.ics', $series));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $this->assertSame(2, substr_count($response->getContent(), 'BEGIN:VEVENT'));
        $response->assertSee('SUMMARY:Cycle One', false);
        $response->assertSee('SUMMARY:Cycle Two', false);
        $response->assertDontSee('SUMMARY:Cycle Past', false);
        $response->assertDontSee('SUMMARY:Cycle Cancelled', false);
        $this->assertNotNull($upcomingA->id);
        $this->assertNotNull($upcomingB->id);
    }

    public function test_private_event_series_ics_returns_not_found_for_guests(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        Event::factory()->private()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
        ]);

        $this->get(route('event-series.calendar.ics', $series))->assertNotFound();
    }

    public function test_event_show_calendar_menu_uses_native_intent_links(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Calendar Menu Event',
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertDontSee('@js($intentUrl)', false)
            ->assertSee(route('events.calendar.ics', $event), false)
            ->assertSee('https://calendar.google.com', false);
    }
}
