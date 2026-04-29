<?php

namespace Tests\Feature;

use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventDuplicatePrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_event_form_prefills_from_duplicate_query_for_owner(): void
    {
        app()->setLocale('en');

        $user = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => null,
            'name' => 'Con Weekend',
            'is_public' => true,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['duplicate' => $event->slug])
            ->test(ManageEventForm::class)
            ->assertSet('name', 'Con Weekend (copy)')
            ->assertSet('duplicateSlotsFromEventId', $event->id)
            ->assertSet('editingEventId', null);
    }

    public function test_non_owner_cannot_open_create_form_prefilled_from_duplicate_param(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'organization_id' => null,
        ]);

        Livewire::actingAs($stranger)
            ->withQueryParams(['duplicate' => $event->slug])
            ->test(ManageEventForm::class)
            ->assertForbidden();
    }
}
