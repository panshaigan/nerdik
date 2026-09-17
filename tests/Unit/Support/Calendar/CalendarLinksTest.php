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
        $place = Place::factory()->venue()->create(['name' => 'Dice Hall']);
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
        $this->assertSame('Dice Hall', $payload->location);
        $this->assertStringContainsString('Bring your character sheet.', $payload->description);
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
