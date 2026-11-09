<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\CreateScheduledActivityForm;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateScheduledActivityFormTest extends TestCase
{
    use RefreshDatabase;

    private function rpgActivityType(): ActivityType
    {
        return ActivityType::query()->where('slug', ActivityType::SLUG_RPG)->first()
            ?? ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_organizer_save_creates_scheduled_activity_and_slot_without_proposal(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $activityName = 'Dungeon Crawl Alpha';
        $startsAt = $event->starts_at->copy()->addHours(2)->format('Y-m-d\TH:i');
        $endsAt = $event->starts_at->copy()->addHours(4)->format('Y-m-d\TH:i');
        $rpg = $this->rpgActivityType();

        Livewire::actingAs($owner)
            ->test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->set('name', $activityName)
            ->set('activity_type_id', $rpg->id)
            ->set('starts_at', $startsAt)
            ->set('ends_at', $endsAt)
            ->call('save')
            ->assertHasNoErrors();

        $slot = Slot::query()->where('event_id', $event->id)->where('name', $activityName)->first();
        $this->assertInstanceOf(Slot::class, $slot);
        $this->assertNotNull($slot->activity_id);

        $activity = Activity::query()->find($slot->activity_id);
        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertSame($activityName, $activity->name);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
        $this->assertSame(0, ActivityProposal::query()->where('event_id', $event->id)->count());
    }

    public function test_guest_cannot_save_and_shell_omits_modal(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertDontSeeHtml('data-ui="schedule-activity-form-host"');

        $rpg = $this->rpgActivityType();

        Livewire::test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->set('name', 'Blocked')
            ->set('activity_type_id', $rpg->id)
            ->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDay()->addHours(2)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertStatus(403);
    }

    public function test_cancelled_event_hides_modal_and_rejects_save(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'cancelled_at' => now(),
            'cancelled_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertDontSeeHtml('livewire:events.create-scheduled-activity-form');

        $rpg = $this->rpgActivityType();

        Livewire::actingAs($owner)
            ->test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->set('name', 'Cancelled block')
            ->set('activity_type_id', $rpg->id)
            ->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDay()->addHours(2)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertStatus(403);
    }

    public function test_organizer_sees_schedule_action_after_event_started_while_propose_hero_hidden(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 14:00:00', 'UTC'));
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => Carbon::parse('2026-06-15 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-06-15 22:00:00', 'UTC'),
        ]);

        $fallback = (string) __('ui.activities.propose_noun_fallback');
        $proposeTitle = __('ui.events.plan_propose_hero_title', ['types' => $fallback]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSeeHtml('data-ui="event-show-plan-schedule-activity"')
            ->assertDontSee($proposeTitle);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-schedule-activity"');
    }

    public function test_validation_requires_venue_when_event_has_places_and_rejects_end_before_start(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $venue = Place::create([
            'name' => 'Main Hall',
            'type' => 'venue',
            'is_online' => false,
        ]);
        $event->places()->attach($venue->id);

        Livewire::actingAs($owner)
            ->test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->set('name', 'Needs venue')
            ->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDay()->addHours(2)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors(['venue_place_id']);

        Livewire::actingAs($owner)
            ->test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->set('name', 'Bad times')
            ->set('venue_place_id', $venue->id)
            ->set('starts_at', now()->addDay()->addHours(4)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDay()->addHours(2)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors(['ends_at']);
    }

    public function test_organizer_can_open_modal_from_event_shell(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(CreateScheduledActivityForm::class, ['eventId' => $event->id])
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSeeHtml('id="event-schedule-activity-form"');

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('openScheduleActivityModal');
    }
}
