<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Events\ManageEventForm;
use App\Livewire\Events\ShowEventSeries;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\User;
use App\Support\Events\EventEditionDateBumper;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_prefills_series_bumps_name_and_shifts_dates_from_latest_edition(): void
    {
        app()->setLocale('en');

        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Porzucane',
            'created_by' => $user->id,
        ]);

        $olderStarts = now()->utc()->addMonths(1)->setTime(18, 30, 0);
        $olderEnds = (clone $olderStarts)->addHours(4);
        $latestStarts = now()->utc()->addMonths(2)->setTime(19, 15, 0);
        $latestEnds = (clone $latestStarts)->addHours(5);
        $windowStarts = (clone $latestStarts)->subDays(7)->setTime(12, 0, 0);
        $windowEnds = (clone $latestEnds)->subHours(1);

        $older = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'event_series_id' => $series->id,
            'name' => 'Porzucane II',
            'is_public' => true,
            'starts_at' => $olderStarts,
            'ends_at' => $olderEnds,
        ]);
        $latest = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'event_series_id' => $series->id,
            'name' => 'Porzucane III',
            'is_public' => true,
            'starts_at' => $latestStarts,
            'ends_at' => $latestEnds,
        ]);
        $latest->enrollmentWindows()->create([
            'name' => 'Main window',
            'starts_at' => $windowStarts,
            'ends_at' => $windowEnds,
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        $dateBumper = app(EventEditionDateBumper::class);
        $expectedStarts = $dateBumper->formatForDatetimeLocal($latestStarts);
        $expectedEnds = $dateBumper->formatForDatetimeLocal($latestEnds);
        $expectedWindowStarts = $dateBumper->formatForDatetimeLocal($windowStarts);
        $expectedWindowEnds = $dateBumper->formatForDatetimeLocal($windowEnds);

        Livewire::actingAs($user)
            ->withQueryParams(['duplicate' => $older->slug])
            ->test(ManageEventForm::class)
            ->assertSet('name', 'Porzucane III')
            ->assertSet('event_series_id', $series->id)
            ->assertSet('event_series_name', 'Porzucane')
            ->assertSet('starts_at', $expectedStarts)
            ->assertSet('ends_at', $expectedEnds)
            ->assertSet('enrollment_windows.0.starts_at', $expectedWindowStarts)
            ->assertSet('enrollment_windows.0.ends_at', $expectedWindowEnds)
            ->assertSet('duplicateSlotsFromEventId', $older->id);
    }

    public function test_saving_event_with_new_series_name_creates_creator_scoped_series(): void
    {
        $user = User::factory()->organizer()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Fresh Con')
            ->set('event_series_name', 'Fresh Cycle')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('is_public', true)
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('search.index'));

        $series = EventSeries::query()->where('name', 'Fresh Cycle')->first();
        $this->assertNotNull($series);
        $this->assertSame($user->id, $series->created_by);

        $event = Event::query()->where('name', 'Fresh Con')->first();
        $this->assertNotNull($event);
        $this->assertSame($series->id, $event->event_series_id);
    }

    public function test_series_show_page_lists_editions_including_cancelled(): void
    {
        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Porzucane',
            'created_by' => $user->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Porzucane I',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addHours(4),
            'cancelled_at' => now()->subMonths(2)->addHour(),
        ]);
        Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Porzucane II',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(4),
        ]);

        $this->get(route('event-series.show', $series))
            ->assertOk()
            ->assertSeeLivewire(ShowEventSeries::class)
            ->assertSee('Porzucane I', false)
            ->assertSee('Porzucane II', false)
            ->assertSeeHtml('data-ui="event-series-edition"')
            ->assertSeeHtml('data-ui="event-card"');

        Livewire::test(ShowEventSeries::class, ['eventSeries' => $series])
            ->assertSet('tab', 'events')
            ->assertSeeHtml('data-ui="event-series-edition-stats"')
            ->assertSeeHtml('data-preserve-scroll');
    }

    public function test_owner_manage_menu_creates_event_by_duplicating_latest_edition(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        $older = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(4),
        ]);
        $latest = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
        ]);

        $html = Livewire::actingAs($owner)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->html();

        $this->assertStringContainsString('data-ui="event-series-show-manage"', $html);
        $this->assertStringContainsString('data-ui="event-series-show-create-event"', $html);
        $this->assertStringContainsString('data-ui="event-series-show-delete"', $html);
        $this->assertStringContainsString(route('events.create', ['duplicate' => $latest->slug]), $html);
        $this->assertStringNotContainsString(route('events.create', ['duplicate' => $older->slug]), $html);
    }

    public function test_stranger_does_not_see_series_manage_menu(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
        ]);

        Livewire::actingAs($stranger)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->assertDontSeeHtml('data-ui="event-series-show-manage"')
            ->assertDontSeeHtml('data-ui="event-series-show-delete"');
    }

    public function test_private_series_is_hidden_from_strangers(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create([
            'created_by' => $owner->id,
        ]);
        Event::factory()->private()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
        ]);

        $this->get(route('event-series.show', $series))->assertNotFound();

        $this->actingAs($owner)
            ->get(route('event-series.show', $series))
            ->assertOk();
    }

    public function test_event_previous_and_next_in_series(): void
    {
        $user = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $user->id]);
        $first = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'A',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(1)->addHours(3),
        ]);
        $second = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'B',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
        ]);
        $third = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'C',
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(3),
            'cancelled_at' => now(),
        ]);

        $this->assertNull($first->previousInSeries());
        $this->assertSame($second->id, $first->nextInSeries()?->id);
        $this->assertSame($first->id, $second->previousInSeries()?->id);
        $this->assertSame($third->id, $second->nextInSeries()?->id);
        $this->assertTrue($second->nextInSeries()?->isCancelled());
    }

    public function test_listing_card_includes_series_link_for_events(): void
    {
        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Porzucane',
            'created_by' => $user->id,
        ]);
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'organization_id' => null,
        ]);

        $card = app(BrowseListingCardPresenter::class)->fromEvent($event, []);

        $this->assertSame('Porzucane', $card->seriesName);
        $this->assertSame(route('event-series.show', $series), $card->seriesUrl);
    }

    public function test_owner_can_delete_series_without_deleting_events(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'name' => 'Kept Edition',
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->call('confirmDeleteSeries')
            ->call('runConfirmedAction')
            ->assertRedirect(route('search.index'));

        $this->assertSoftDeleted($series);
        $event->refresh();
        $this->assertNull($event->event_series_id);
        $this->assertNull($event->deleted_at);
    }

    public function test_stranger_cannot_delete_series(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
        ]);

        Livewire::actingAs($stranger)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->call('deleteSeries')
            ->assertForbidden();

        $this->assertDatabaseHas('event_series', ['id' => $series->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_series(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->call('deleteSeries')
            ->assertRedirect(route('search.index'));

        $this->assertSoftDeleted($series);
    }

    public function test_follow_toggles_interest_on_upcoming_editions_only(): void
    {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $series = EventSeries::factory()->create(['created_by' => $owner->id]);
        $past = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(4),
        ]);
        $upcomingA = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
        ]);
        $upcomingB = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $series->id,
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(4),
        ]);

        $component = Livewire::actingAs($follower)
            ->test(ShowEventSeries::class, ['eventSeries' => $series]);

        $component->call('toggleUpcomingInterest');
        $this->assertTrue($follower->interestedEvents()->whereKey($upcomingA->id)->exists());
        $this->assertTrue($follower->interestedEvents()->whereKey($upcomingB->id)->exists());
        $this->assertFalse($follower->interestedEvents()->whereKey($past->id)->exists());

        $component->call('toggleUpcomingInterest');
        $this->assertFalse($follower->interestedEvents()->whereKey($upcomingA->id)->exists());
        $this->assertFalse($follower->interestedEvents()->whereKey($upcomingB->id)->exists());
    }
}
