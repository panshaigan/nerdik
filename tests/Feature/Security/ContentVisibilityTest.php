<?php

namespace Tests\Feature\Security;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Activities\ShowActivity;
use App\Livewire\Events\EventShowMapTab;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\ShowEvent;
use App\Livewire\Events\ShowEventSeries;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, int}> */
    public static function viewers(): array
    {
        return ['guest' => ['guest', 404], 'unrelated' => ['unrelated', 404], 'owner' => ['owner', 200], 'admin' => ['admin', 200]];
    }

    #[DataProvider('viewers')]
    public function test_private_event_page_and_calendar_enforce_visibility(string $role, int $status): void
    {
        $owner = User::factory()->organizer()->create();
        $event = Event::factory()->private()->create(['created_by' => $owner->id, 'starts_at' => now()->addDay()]);
        if ($role !== 'guest') {
            $this->actingAs(match ($role) {
                'owner' => $owner,
                'admin' => User::factory()->admin()->create(),
                default => User::factory()->create(),
            });
        }
        $this->get(route('events.show', $event))->assertStatus($status);
        $this->get(route('events.calendar.ics', $event))->assertStatus($status);
    }

    public function test_public_event_page_and_calendar_remain_public(): void
    {
        $event = Event::factory()->public()->create(['starts_at' => now()->addDay()]);
        $this->get(route('events.show', $event))->assertOk();
        $this->get(route('events.calendar.ics', $event))->assertOk();
    }

    public function test_guest_cannot_mount_hidden_activity_directly(): void
    {
        $activity = Activity::factory()->proposed()->create();
        $this->get(route('activities.show', $activity))->assertNotFound();
        $this->get(route('activities.calendar.ics', $activity))->assertNotFound();
        Livewire::test(ShowActivity::class, ['activity' => $activity])->assertNotFound();
    }

    public function test_activity_identifier_cannot_be_replaced(): void
    {
        $visible = Activity::factory()->selfHosted()->create();
        $hidden = Activity::factory()->proposed()->create();
        $component = Livewire::test(ShowActivity::class, ['activity' => $visible]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('activityId', $hidden->id);
    }

    public function test_event_identifier_cannot_be_replaced(): void
    {
        $visible = Event::factory()->public()->create();
        $hidden = Event::factory()->private()->create();
        $component = Livewire::test(ShowEvent::class, ['event' => $visible]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('eventId', $hidden->id);
    }

    /** @return array<string, array{class-string}> */
    public static function eventComponents(): array
    {
        return ['shell' => [ShowEvent::class], 'plan' => [EventShowPlanTab::class], 'map' => [EventShowMapTab::class]];
    }

    #[DataProvider('eventComponents')]
    public function test_mounted_event_components_recheck_visibility(string $componentClass): void
    {
        $event = Event::factory()->public()->create();
        $parameters = $componentClass === ShowEvent::class ? ['event' => $event] : ['eventId' => $event->id];
        $component = Livewire::withoutLazyLoading()->test($componentClass, $parameters);
        $event->update(['is_public' => false]);
        $this->expectException(ModelNotFoundException::class);
        $component->call('$refresh');
    }

    public function test_mounted_activity_rechecks_parent_event_visibility(): void
    {
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activity->id]);
        $component = Livewire::test(ShowActivity::class, ['activity' => $activity]);
        $event->update(['is_public' => false]);
        $this->expectException(ModelNotFoundException::class);
        $component->call('$refresh');
    }

    public function test_public_event_preview_does_not_reveal_pending_proposals_to_guests(): void
    {
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->proposed()->create();
        ActivityProposal::factory()->create(['activity_id' => $activity->id, 'event_id' => $event->id, 'status' => ActivityProposalStatus::Pending]);
        Livewire::test(ShowEvent::class, ['event' => $event])
            ->call('openActivityPreview', $activity->id)
            ->assertSet('previewActivityId', null)
            ->assertSet('activityPreviewModalOpen', false);
        Livewire::actingAs($event->creator)->test(ShowEvent::class, ['event' => $event])
            ->call('openActivityPreview', $activity->id)
            ->assertSet('previewActivityId', $activity->id);
    }

    public function test_series_ownership_does_not_grant_access_to_another_owners_private_event(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        $public = Event::factory()->public()->create(['event_series_id' => $series->id, 'starts_at' => now()->addDay()]);
        $private = Event::factory()->private()->create(['event_series_id' => $series->id, 'starts_at' => now()->addDays(2)]);
        Livewire::actingAs($owner)->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->assertViewHas('events', fn ($events): bool => $events->contains($public) && ! $events->contains($private));
        $this->actingAs($owner)->get(route('event-series.calendar.ics', $series))
            ->assertOk()->assertSee($public->name)->assertDontSee($private->name);
    }

    public function test_private_event_preview_is_denied_even_when_its_series_is_public(): void
    {
        $series = EventSeries::factory()->create();
        Event::factory()->public()->create(['event_series_id' => $series->id]);
        $private = Event::factory()->private()->create(['event_series_id' => $series->id]);
        $component = Livewire::test(ShowEventSeries::class, ['eventSeries' => $series]);
        $this->expectException(ModelNotFoundException::class);
        $component->call('openEventPreview', $private->id);
    }
}
