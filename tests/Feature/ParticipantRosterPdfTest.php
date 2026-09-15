<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
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

    public function test_activity_owner_can_download_participants_pdf(): void
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
            'attachment; filename=',
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

    public function test_event_owner_can_download_participants_pdf(): void
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

    public function test_activity_roster_includes_games_host_and_absent_flag(): void
    {
        $owner = User::factory()->create(['nickname' => 'gm_host']);
        $participant = User::factory()->create(['nickname' => 'alice_player']);
        $absentParticipant = User::factory()->create(['nickname' => 'bob_missing']);

        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $gameTag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        TagTranslation::factory()->create([
            'tag_id' => $gameTag->id,
            'locale' => 'en',
            'label' => 'Blades in the Dark',
        ]);

        $activity = Activity::factory()->create([
            'name' => 'Midnight Heist',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $activity->tags()->attach($gameTag->id);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $absentParticipant->id,
            'is_absent' => true,
        ]);

        $roster = app(ParticipantRosterPdfBuilder::class)->activityRoster($activity);

        $this->assertSame('Midnight Heist', $roster['name']);
        $this->assertSame('gm_host', $roster['host']);
        $this->assertSame(['Blades in the Dark'], $roster['gameNames']);
        $this->assertCount(2, $roster['participants']);
        $this->assertSame('alice_player', $roster['participants'][0]['name']);
        $this->assertFalse($roster['participants'][0]['is_absent']);
        $this->assertSame('bob_missing', $roster['participants'][1]['name']);
        $this->assertTrue($roster['participants'][1]['is_absent']);

        $html = view('pdf.activity-participants', ['roster' => $roster])->render();
        $this->assertStringContainsString('Midnight Heist', $html);
        $this->assertStringContainsString('Blades in the Dark', $html);
        $this->assertStringContainsString('alice_player', $html);
        $this->assertStringContainsString('bob_missing', $html);
        $this->assertStringContainsString(__('ui.pdf.roster.absent_note'), $html);
    }

    public function test_event_roster_includes_active_activities_and_skips_cancelled(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'name' => 'Con Alpha',
            'created_by' => $owner->id,
        ]);

        $active = Activity::factory()->scheduled()->create([
            'name' => 'Active Table',
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
            'event_id' => $event->id,
            'activity_id' => $active->id,
            'starts_at' => now()->addDay(),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $cancelled->id,
            'starts_at' => now()->addDays(2),
        ]);

        $roster = app(ParticipantRosterPdfBuilder::class)->eventRoster($event);

        $this->assertSame('Con Alpha', $roster['eventName']);
        $this->assertCount(1, $roster['activities']);
        $this->assertSame('Active Table', $roster['activities'][0]['name']);

        $html = view('pdf.event-participants', ['roster' => $roster])->render();
        $this->assertStringContainsString('Con Alpha', $html);
        $this->assertStringContainsString('Active Table', $html);
        $this->assertStringNotContainsString('Cancelled Table', $html);
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
