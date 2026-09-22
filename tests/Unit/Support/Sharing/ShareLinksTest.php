<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Sharing;

use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Place;
use App\Models\User;
use App\Support\Sharing\ShareLinks;
use App\Support\Sharing\ShareTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShareLinksTest extends TestCase
{
    use RefreshDatabase;

    private ShareLinks $shareLinks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shareLinks = app(ShareLinks::class);
    }

    public function test_for_event_returns_null_when_cancelled(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->assertNull($this->shareLinks->forEvent($event));
    }

    public function test_for_activity_returns_null_when_cancelled(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(3)->setSecond(0);
        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);

        $this->assertNull($this->shareLinks->forActivity($activity));
    }

    public function test_for_event_builds_canonical_payload_without_utms(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Shareable Con',
            'description' => '<p>Great games await.</p>',
        ]);

        $payload = $this->shareLinks->forEvent($event);

        $this->assertNotNull($payload);
        $this->assertSame('Shareable Con', $payload->title);
        $this->assertSame(route('events.show', $event), $payload->url);
        $this->assertSame('event', $payload->campaign);
        $this->assertStringContainsString('Great games await.', $payload->text);
        $this->assertStringNotContainsString('utm_', $payload->url);
    }

    public function test_for_activity_builds_canonical_payload_without_utms(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(5)->setSecond(0);
        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'name' => 'Shareable Session',
            'description' => '<p>One-shot adventure.</p>',
        ]);

        $payload = $this->shareLinks->forActivity($activity);

        $this->assertNotNull($payload);
        $this->assertSame('Shareable Session', $payload->title);
        $this->assertSame(route('activities.show', $activity), $payload->url);
        $this->assertSame('activity', $payload->campaign);
        $this->assertStringContainsString('One-shot adventure.', $payload->text);
        $this->assertStringNotContainsString('utm_', $payload->url);
    }

    public function test_tracked_url_appends_utm_params_for_each_target(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'UTM Event',
        ]);
        $payload = $this->shareLinks->forEvent($event);
        $this->assertNotNull($payload);

        foreach (ShareTarget::cases() as $target) {
            $tracked = $this->shareLinks->trackedUrl($payload, $target);
            $query = [];
            parse_str((string) parse_url($tracked, PHP_URL_QUERY), $query);

            $this->assertSame('nerdik', $query['utm_source']);
            $this->assertSame('share', $query['utm_medium']);
            $this->assertSame('event', $query['utm_campaign']);
            $this->assertSame($target->value, $query['utm_content']);
            $this->assertStringStartsWith(route('events.show', $event), $tracked);
        }
    }

    public function test_intent_urls_encode_tracked_destination_for_external_targets(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Intent Event',
        ]);
        $payload = $this->shareLinks->forEvent($event);
        $this->assertNotNull($payload);

        $this->assertNull($this->shareLinks->intentUrl($payload, ShareTarget::Copy));
        $this->assertNull($this->shareLinks->intentUrl($payload, ShareTarget::Instagram));
        $this->assertTrue(ShareTarget::Instagram->usesClipboard());
        $this->assertContains(ShareTarget::Instagram, ShareTarget::menuPlatformCases());

        $facebook = $this->shareLinks->intentUrl($payload, ShareTarget::Facebook);
        $this->assertNotNull($facebook);
        $this->assertStringStartsWith('https://www.facebook.com/sharer/sharer.php?', $facebook);
        $this->assertStringContainsString(rawurlencode($this->shareLinks->trackedUrl($payload, ShareTarget::Facebook)), $facebook);

        $whatsapp = $this->shareLinks->intentUrl($payload, ShareTarget::WhatsApp);
        $this->assertNotNull($whatsapp);
        $this->assertStringStartsWith('https://wa.me/?', $whatsapp);
        $this->assertStringContainsString('text=', $whatsapp);

        $x = $this->shareLinks->intentUrl($payload, ShareTarget::X);
        $this->assertNotNull($x);
        $this->assertStringStartsWith('https://twitter.com/intent/tweet?', $x);

        $telegram = $this->shareLinks->intentUrl($payload, ShareTarget::Telegram);
        $this->assertNotNull($telegram);
        $this->assertStringStartsWith('https://t.me/share/url?', $telegram);
    }

    public function test_menu_urls_use_first_party_redirect_to_avoid_adblockers(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Menu URL Event',
        ]);
        $payload = $this->shareLinks->forEvent($event);
        $this->assertNotNull($payload);

        $this->assertNull($this->shareLinks->menuUrl($payload, ShareTarget::Copy));

        foreach (ShareTarget::externalCases() as $target) {
            $menuUrl = $this->shareLinks->menuUrl($payload, $target);
            $this->assertNotNull($menuUrl);
            $this->assertStringStartsWith(route('share.redirect', ['target' => $target->value]), $menuUrl);
            $this->assertStringNotContainsString('facebook.com', $menuUrl);
            $this->assertStringNotContainsString('wa.me', $menuUrl);
            $this->assertStringNotContainsString('twitter.com', $menuUrl);
            $this->assertStringNotContainsString('t.me', $menuUrl);
        }
    }

    public function test_for_event_series_builds_canonical_payload_without_utms(): void
    {
        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'created_by' => $user->id,
            'name' => 'Shareable Cycle',
            'description' => '<p>Yearly gathering.</p>',
        ]);

        $payload = $this->shareLinks->forEventSeries($series);

        $this->assertSame('Shareable Cycle', $payload->title);
        $this->assertSame(route('event-series.show', $series), $payload->url);
        $this->assertSame('event-series', $payload->campaign);
        $this->assertStringContainsString('Yearly gathering.', $payload->text);
        $this->assertStringNotContainsString('utm_', $payload->url);
    }
}
