<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Calendar;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Support\Calendar\CalendarLinks;
use App\Support\Calendar\CalendarTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CalendarLinksTest extends TestCase
{
    use RefreshDatabase;

    private CalendarLinks $calendarLinks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendarLinks = app(CalendarLinks::class);
    }

    public function test_for_event_returns_null_when_cancelled(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->assertNull($this->calendarLinks->forEvent($event));
    }

    public function test_for_activity_returns_null_when_cancelled(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(3)->setSecond(0);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->assertNull($this->calendarLinks->forActivity($activity));
    }

    public function test_for_activity_returns_null_without_schedule_start(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertNull($this->calendarLinks->forActivity($activity));
    }

    public function test_for_event_builds_single_block_spanning_multi_day_range(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addWeek()->setTime(10, 0)->utc();
        $endsAt = $startsAt->copy()->addDays(2)->setTime(18, 0);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Weekend Con',
            'description' => '<p>Board games all weekend.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $payload = $this->calendarLinks->forEvent($event);

        $this->assertNotNull($payload);
        $this->assertSame('Weekend Con', $payload->title);
        $this->assertSame(route('events.show', $event), $payload->url);
        $this->assertSame(route('events.calendar.ics', $event), $payload->icsDownloadUrl);
        $this->assertTrue($payload->startsAt->equalTo($startsAt));
        $this->assertTrue($payload->endsAt->equalTo($endsAt));
        $this->assertStringContainsString('event-'.$event->id.'@', $payload->uid);
        $this->assertStringContainsString('Board games all weekend.', $payload->description);
    }

    public function test_for_activity_builds_payload_from_self_hosted_schedule(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'name' => 'Dice Hall',
            'address' => 'Main Street 10',
            'latitude' => 52.2297,
            'longitude' => 21.0122,
        ]);
        $startsAt = now()->addDays(5)->utc()->startOfMinute();
        $endsAt = $startsAt->copy()->addHours(3);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'name' => 'One Shot Night',
            'description' => '<p>Bring your character sheet.</p>',
        ]);

        $payload = $this->calendarLinks->forActivity($activity);

        $this->assertNotNull($payload);
        $this->assertSame('One Shot Night', $payload->title);
        $this->assertSame(route('activities.show', $activity), $payload->url);
        $this->assertSame(route('activities.calendar.ics', $activity), $payload->icsDownloadUrl);
        $this->assertSame($startsAt->toIso8601String(), $payload->startsAt->toIso8601String());
        $this->assertSame($endsAt->toIso8601String(), $payload->endsAt->toIso8601String());
        $this->assertSame('Dice Hall, Main Street 10', $payload->location);
        $this->assertSame(52.2297, $payload->latitude);
        $this->assertSame(21.0122, $payload->longitude);
        $this->assertStringContainsString('Bring your character sheet.', $payload->description);
    }

    public function test_for_activity_uses_venue_without_room_in_location(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create([
            'name' => 'Convention Center',
            'address' => 'Expo Road 1',
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);
        $room = Place::factory()->room($venue)->create([
            'name' => 'Room A',
            'created_by' => $user->id,
        ]);
        $startsAt = now()->addDays(2)->utc()->startOfMinute();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $room->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'name' => 'Panel in Room A',
        ]);

        $payload = $this->calendarLinks->forActivity($activity);

        $this->assertNotNull($payload);
        $this->assertSame('Convention Center, Expo Road 1', $payload->location);
        $this->assertStringNotContainsString('Room A', $payload->location);
        $this->assertSame(51.1, $payload->latitude);
        $this->assertSame(17.0, $payload->longitude);
    }

    public function test_for_event_includes_venue_address_and_coordinates(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create([
            'name' => 'Festival Hall',
            'address' => 'River Quay 5',
            'latitude' => 50.0614,
            'longitude' => 19.9370,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Geo Event',
        ]);
        $event->places()->attach($venue->id);

        $payload = $this->calendarLinks->forEvent($event);

        $this->assertNotNull($payload);
        $this->assertSame('Festival Hall, River Quay 5', $payload->location);
        $this->assertSame(50.0614, $payload->latitude);
        $this->assertSame(19.9370, $payload->longitude);

        $ics = $this->calendarLinks->icsContent($payload);
        $this->assertStringContainsString('LOCATION:Festival Hall\, River Quay 5', $ics);
        $this->assertStringContainsString('GEO:50.0614;19.937', $ics);
    }

    public function test_multi_venue_event_joins_locations_without_geo(): void
    {
        $user = User::factory()->create();
        $first = Place::factory()->venue()->create([
            'name' => 'Hall A',
            'address' => 'Street 1',
            'latitude' => 51.0,
            'longitude' => 17.0,
        ]);
        $second = Place::factory()->venue()->create([
            'name' => 'Hall B',
            'address' => 'Street 2',
            'latitude' => 51.1,
            'longitude' => 17.1,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Two Venue Event',
        ]);
        $event->places()->attach([$first->id, $second->id]);

        $payload = $this->calendarLinks->forEvent($event);

        $this->assertNotNull($payload);
        $this->assertSame('Hall A, Street 1; Hall B, Street 2', $payload->location);
        $this->assertNull($payload->latitude);
        $this->assertNull($payload->longitude);
    }

    public function test_google_intent_url_appends_coordinates_to_location(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create([
            'name' => 'Pin Hall',
            'address' => 'Map Street 3',
            'latitude' => 52.25,
            'longitude' => 21.0,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Pinned Event',
        ]);
        $event->places()->attach($venue->id);
        $payload = $this->calendarLinks->forEvent($event);
        $this->assertNotNull($payload);

        $url = $this->calendarLinks->intentUrl($payload, CalendarTarget::Google);
        $this->assertNotNull($url);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('Pin Hall, Map Street 3, 52.25, 21', $query['location']);
    }

    public function test_google_intent_url_includes_utc_dates_and_title(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addWeek()->setTime(12, 0)->utc();
        $endsAt = $startsAt->copy()->addHours(4);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Google Export Event',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $payload = $this->calendarLinks->forEvent($event);
        $this->assertNotNull($payload);

        $url = $this->calendarLinks->intentUrl($payload, CalendarTarget::Google);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('TEMPLATE', $query['action']);
        $this->assertSame('Google Export Event', $query['text']);
        $this->assertSame(
            $startsAt->format('Ymd\THis\Z').'/'.$endsAt->format('Ymd\THis\Z'),
            $query['dates'],
        );
    }

    public function test_outlook_intent_url_includes_iso_datetimes(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addWeek()->setTime(14, 30)->utc();
        $endsAt = $startsAt->copy()->addHours(2);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Outlook Export Event',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $payload = $this->calendarLinks->forEvent($event);
        $this->assertNotNull($payload);

        $url = $this->calendarLinks->intentUrl($payload, CalendarTarget::Outlook);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://outlook.live.com/calendar/0/deeplink/compose?', $url);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('Outlook Export Event', $query['subject']);
        $this->assertNotEmpty($query['startdt']);
        $this->assertNotEmpty($query['enddt']);
    }

    public function test_download_intent_url_points_at_ics_route(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'ICS Download Event',
        ]);
        $payload = $this->calendarLinks->forEvent($event);
        $this->assertNotNull($payload);

        $this->assertSame(
            $payload->icsDownloadUrl,
            $this->calendarLinks->intentUrl($payload, CalendarTarget::Download),
        );
    }

    public function test_ics_content_contains_vevent_block(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addDays(2)->setTime(9, 0)->utc();
        $endsAt = $startsAt->copy()->addDays(1)->setTime(17, 0);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'ICS Content Event',
            'description' => '<p>Hello, world.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $payload = $this->calendarLinks->forEvent($event);
        $this->assertNotNull($payload);

        $ics = $this->calendarLinks->icsContent($payload);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('SUMMARY:ICS Content Event', $ics);
        $this->assertStringContainsString('DTSTART:'.$startsAt->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('DTEND:'.$endsAt->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('UID:'.$payload->uid, $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
    }
}
