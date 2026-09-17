<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Services\ParticipantRosterPdfBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ParticipantRosterPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_owner_can_stream_participants_pdf_inline(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create(['nickname' => 'alice_player']);
        $activity = Activity::factory()->create([
            'name' => 'Midnight Heist',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('activities.participants.pdf', $activity));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'inline; filename=',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_guest_cannot_download_activity_participants_pdf(): void
    {
        $activity = Activity::factory()->create();

        $this->get(route('activities.participants.pdf', $activity))
            ->assertRedirect();
    }

    public function test_non_owner_cannot_download_activity_participants_pdf(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('activities.participants.pdf', $activity))
            ->assertForbidden();
    }

    public function test_event_owner_can_stream_participants_pdf_inline(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'name' => 'Con Alpha',
            'created_by' => $owner->id,
        ]);
        $activity = Activity::factory()->scheduled()->create([
            'name' => 'Table One',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('events.participants.pdf', $event));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'inline; filename=',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_non_owner_cannot_download_event_participants_pdf(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $this->actingAs($other)
            ->get(route('events.participants.pdf', $event))
            ->assertForbidden();
    }

    public function test_activity_roster_includes_meta_sorts_participants_and_uses_singular_game(): void
    {
        $owner = User::factory()->create(['nickname' => 'gm_host']);
        $charlie = User::factory()->create(['nickname' => 'charlie_player']);
        $alice = User::factory()->create(['nickname' => 'alice_player']);
        $bob = User::factory()->create(['nickname' => 'bob_missing']);

        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $gameTag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        TagTranslation::factory()->create([
            'tag_id' => $gameTag->id,
            'locale' => 'en',
            'label' => 'Blades in the Dark',
        ]);

        $venue = Place::factory()->venue()->create(['name' => 'Main Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room 12']);

        $activity = Activity::factory()->create([
            'name' => 'Midnight Heist',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $activity->tags()->attach($gameTag->id);

        $startsAt = now()->addDay()->setTime(14, 0);
        $endsAt = $startsAt->copy()->addHours(4);

        Slot::factory()->create([
            'name' => 'Slot Alpha',
            'activity_id' => $activity->id,
            'place_id' => $room->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => $owner->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $charlie->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $alice->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $bob->id,
            'is_absent' => true,
        ]);

        $activity->refresh();
        $roster = app(ParticipantRosterPdfBuilder::class)->activityRoster($activity);

        $this->assertSame('Midnight Heist', $roster['name']);
        $this->assertSame('Midnight Heist - Participant roster', $roster['documentTitle']);
        $this->assertSame('gm_host', $roster['host']);
        $this->assertSame(['Blades in the Dark'], $roster['gameNames']);
        $this->assertSame('Slot Alpha', $roster['slotName']);
        $this->assertSame('Slot Alpha · Room 12', $roster['where']);
        $this->assertNotNull($roster['when']);
        $this->assertSame(['alice_player', 'bob_missing', 'charlie_player'], array_column($roster['participants'], 'name'));
        $this->assertTrue($roster['participants'][1]['is_absent']);

        $html = view('pdf.activity-participants', ['roster' => $roster])->render();
        $this->assertStringContainsString('Midnight Heist - Participant roster', $html);
        $this->assertStringContainsString(__('ui.pdf.roster.game').':', $html);
        $this->assertStringContainsString('Blades in the Dark', $html);
        $this->assertStringContainsString('Slot Alpha · Room 12', $html);
        $this->assertStringNotContainsString(__('ui.pdf.roster.where').':', $html);
        $this->assertStringNotContainsString(__('ui.pdf.roster.when').':', $html);
        $this->assertStringContainsString(__('ui.pdf.roster.absent_note'), $html);
        $this->assertMatchesRegularExpression(
            '/col-name.*col-check/s',
            $html,
        );
        $this->assertStringContainsString('activity-table-sign', $html);
        $this->assertStringContainsString('sign-slot', $html);
        $this->assertStringContainsString('Slot Alpha', $html);
        $this->assertStringContainsString('sign-session', $html);
        $this->assertStringContainsString('Midnight Heist', $html);
        $this->assertStringContainsString('sign-game', $html);
    }

    public function test_event_roster_includes_where_when_three_column_layout_and_skips_cancelled(): void
    {
        $owner = User::factory()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Expo Center']);
        $event = Event::factory()->public()->create([
            'name' => 'Con Alpha',
            'created_by' => $owner->id,
            'starts_at' => now()->addWeek()->setTime(10, 0),
            'ends_at' => now()->addWeek()->setTime(22, 0),
        ]);
        $event->places()->attach($venue->id);

        $active = Activity::factory()->scheduled()->create([
            'name' => 'Active Table',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $second = Activity::factory()->scheduled()->create([
            'name' => 'Second Table',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $cancelled = Activity::factory()->scheduled()->create([
            'name' => 'Cancelled Table',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'cancelled_at' => now(),
        ]);

        Slot::factory()->create([
            'name' => 'Zebra Slot',
            'event_id' => $event->id,
            'activity_id' => $active->id,
            'starts_at' => now()->addWeek()->setTime(12, 0),
        ]);
        Slot::factory()->create([
            'name' => 'Alpha Slot',
            'event_id' => $event->id,
            'activity_id' => $second->id,
            'starts_at' => now()->addWeek()->setTime(14, 0),
        ]);
        Slot::factory()->create([
            'name' => 'Middle Slot',
            'event_id' => $event->id,
            'activity_id' => $cancelled->id,
            'starts_at' => now()->addWeek()->setTime(16, 0),
        ]);

        $roster = app(ParticipantRosterPdfBuilder::class)->eventRoster($event->fresh(['places.city']));

        $this->assertSame('Con Alpha', $roster['eventName']);
        $this->assertSame('Con Alpha - Participant roster', $roster['documentTitle']);
        $this->assertSame('Expo Center', $roster['where']);
        $this->assertNotNull($roster['when']);
        $this->assertStringContainsString(':', $roster['when']);
        $this->assertCount(2, $roster['activities']);
        $this->assertSame('Second Table', $roster['activities'][0]['name']);
        $this->assertSame('Active Table', $roster['activities'][1]['name']);
        $this->assertStringStartsWith('Alpha Slot', (string) $roster['activities'][0]['where']);
        $this->assertStringStartsWith('Zebra Slot', (string) $roster['activities'][1]['where']);

        $html = view('pdf.event-participants', ['roster' => $roster])->render();
        $this->assertStringContainsString('Con Alpha - Participant roster', $html);
        $this->assertStringContainsString('Expo Center', $html);
        $this->assertStringContainsString('activities-grid', $html);
        $this->assertStringContainsString('Active Table', $html);
        $this->assertStringContainsString('Second Table', $html);
        $this->assertStringNotContainsString('Cancelled Table', $html);
        $this->assertStringNotContainsString(__('ui.pdf.roster.where').':', $html);
        $this->assertStringNotContainsString(__('ui.pdf.roster.when').':', $html);
        $this->assertSame(2, substr_count($html, 'class="activity-table-sign"'));
        $this->assertStringContainsString('Alpha Slot', $html);
        $this->assertStringContainsString('Zebra Slot', $html);
        $this->assertStringNotContainsString('Middle Slot', $html);
    }

    public function test_admin_can_download_activity_participants_pdf(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.participants.pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
