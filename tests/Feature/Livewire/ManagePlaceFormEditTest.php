<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Places\ManagePlaceForm;
use App\Models\Activity;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManagePlaceFormEditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stranger_cannot_mount_edit_form(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Owned Venue',
        ]);

        Livewire::actingAs($stranger)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_update_place_name_and_location(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Before Update Venue',
            'address' => 'Old Street 1',
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        Livewire::actingAs($owner)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->set('name', 'After Update Venue')
            ->set('address', 'New Street 2')
            ->set('latitude', 52.2)
            ->set('longitude', 21.0)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('catalog.places'));

        $place->refresh();
        $this->assertSame('After Update Venue', $place->name);
        $this->assertSame('New Street 2', $place->address);
        $this->assertEqualsWithDelta(52.2, (float) $place->latitude, 0.0001);
        $this->assertEqualsWithDelta(21.0, (float) $place->longitude, 0.0001);
    }

    #[Test]
    public function owner_can_add_rename_and_remove_rooms(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Rooms Venue',
        ]);
        $existingRoom = Place::factory()->room($place)->create([
            'created_by' => $owner->id,
            'name' => 'Old Room',
        ]);
        $removableRoom = Place::factory()->room($place)->create([
            'created_by' => $owner->id,
            'name' => 'Removable Room',
        ]);

        Livewire::actingAs($owner)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->set('rooms.0.name', 'Renamed Room')
            ->call('removeRoom', 'room-'.$removableRoom->id)
            ->call('addRoom')
            ->set('rooms.1.name', 'Brand New Room')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('catalog.places'));

        $existingRoom->refresh();
        $this->assertSame('Renamed Room', $existingRoom->name);
        $this->assertSoftDeleted($removableRoom);

        $this->assertDatabaseHas('places', [
            'parent_id' => $place->id,
            'type' => Place::TYPE_ROOM,
            'name' => 'Brand New Room',
        ]);
    }

    #[Test]
    public function owner_cannot_remove_room_that_is_in_use(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Busy Venue',
        ]);
        $room = Place::factory()->room($place)->create([
            'created_by' => $owner->id,
            'name' => 'Busy Room',
        ]);

        Activity::factory()->create([
            'created_by' => $owner->id,
            'place_id' => $room->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->call('removeRoom', 'room-'.$room->id)
            ->call('save')
            ->assertHasErrors(['rooms']);

        $this->assertNotSoftDeleted($room);
    }

    #[Test]
    public function admin_can_edit_place_owned_by_someone_else(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Admin Editable Venue',
        ]);

        Livewire::actingAs($admin)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->set('name', 'Admin Updated Venue')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('catalog.places'));

        $this->assertSame('Admin Updated Venue', $place->fresh()->name);
    }

    #[Test]
    public function edit_page_renders_for_owner(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Page Render Venue',
        ]);

        $this->actingAs($owner)
            ->get(route('places.edit', $place))
            ->assertOk()
            ->assertSeeHtml('data-ui="place-form-fields"')
            ->assertSeeHtml('data-place-location-map')
            ->assertSeeHtml('data-ui="place-form-rooms"');
    }

    #[Test]
    public function room_in_use_by_slot_blocks_removal(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Slot Venue',
        ]);
        $room = Place::factory()->room($place)->create([
            'created_by' => $owner->id,
            'name' => 'Slot Room',
        ]);

        Slot::factory()->create([
            'created_by' => $owner->id,
            'place_id' => $room->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManagePlaceForm::class, ['place' => $place])
            ->call('removeRoom', 'room-'.$room->id)
            ->call('save')
            ->assertHasErrors(['rooms']);

        $this->assertNotSoftDeleted($room);
    }
}
