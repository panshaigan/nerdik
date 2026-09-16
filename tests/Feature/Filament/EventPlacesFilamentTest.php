<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Events\Pages\EditEvent;
use App\Filament\Admin\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventPlacesFilamentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function event_edit_form_displays_attached_place_names(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Filament Event Venue']);
        $event = Event::factory()->create();
        $event->places()->attach($venue);

        Livewire::actingAs($admin)
            ->test(EditEvent::class, ['record' => $event->slug])
            ->assertOk()
            ->assertSee('Filament Event Venue');
    }

    #[Test]
    public function admin_can_sync_event_places_through_filament_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $existingVenue = Place::factory()->venue()->create(['name' => 'Existing Venue']);
        $newVenue = Place::factory()->venue()->create(['name' => 'Replacement Venue']);
        $event = Event::factory()->create();
        $event->places()->attach($existingVenue);

        Livewire::actingAs($admin)
            ->test(EditEvent::class, ['record' => $event->slug])
            ->fillForm([
                'places' => [$newVenue->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$newVenue->id],
            $event->fresh()->places()->pluck('places.id')->all(),
        );
    }

    #[Test]
    public function event_places_select_lists_venues_without_rooms(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Unique Venue For Event Places']);
        Place::factory()->room($venue)->create(['name' => 'Should Not Appear Room']);
        $event = Event::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditEvent::class, ['record' => $event->slug])
            ->assertOk()
            ->call('callSchemaComponentMethod', 'form.places', 'getSearchResultsForJs', ['Unique Venue For Event Places'])
            ->assertReturned(fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['label'] === 'Unique Venue For Event Places'
                    && $option['value'] === (string) $venue->id,
            ) && collect($options)->doesntContain(
                fn (array $option): bool => str_contains((string) $option['label'], 'Should Not Appear Room'),
            ));
    }

    #[Test]
    public function events_table_shows_attached_place_names(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Listed Event Venue']);
        $event = Event::factory()->create(['name' => 'Event With Venue']);
        $event->places()->attach($venue);

        Livewire::actingAs($admin)
            ->test(ListEvents::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$event])
            ->assertSee('Listed Event Venue');
    }
}
