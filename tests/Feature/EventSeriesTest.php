<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Events\ManageEventForm;
use App\Livewire\Events\ShowEventSeries;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\User;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_prefills_series_and_bumps_roman_edition_name(): void
    {
        app()->setLocale('en');

        $user = User::factory()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Porzucane',
            'created_by' => $user->id,
        ]);
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'event_series_id' => $series->id,
            'name' => 'Porzucane II',
            'is_public' => true,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['duplicate' => $event->slug])
            ->test(ManageEventForm::class)
            ->assertSet('name', 'Porzucane III')
            ->assertSet('event_series_id', $series->id)
            ->assertSet('event_series_name', 'Porzucane')
            ->assertSet('duplicateSlotsFromEventId', $event->id);
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
        $first = Event::factory()->public()->create([
            'created_by' => $user->id,
            'event_series_id' => $series->id,
            'name' => 'Porzucane I',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addHours(4),
            'cancelled_at' => now()->subMonths(2)->addHour(),
        ]);
        $second = Event::factory()->public()->create([
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
            ->assertSee(route('events.show', $first), false)
            ->assertSee(route('events.show', $second), false);

        Livewire::test(ShowEventSeries::class, ['eventSeries' => $series])
            ->assertSet('tab', 'events')
            ->assertSee(__('ui.events.cancelled_short'), false);
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
}
