<?php

namespace Tests\Feature;

use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\EventSeries;
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

    public function test_admin_duplicates_another_users_event_keeping_original_creator_and_series(): void
    {
        app()->setLocale('en');

        $owner = User::factory()->organizer()->create();
        $admin = User::factory()->admin()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Owner Con',
            'created_by' => $owner->id,
        ]);
        $source = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $series->id,
            'name' => 'Owner Weekend',
            'is_public' => true,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        Livewire::actingAs($admin)
            ->withQueryParams(['duplicate' => $source->slug])
            ->test(ManageEventForm::class)
            ->assertSet('name', 'Owner Weekend (copy)')
            ->assertSet('event_series_id', $series->id)
            ->assertSet('duplicateSlotsFromEventId', $source->id)
            ->set('enrollment_windows', [[
                'name' => 'Window 1',
                'starts_at' => now()->addDays(10)->format(datetime_local_input_format()),
                'ends_at' => now()->addDays(20)->format(datetime_local_input_format()),
                'max_activities_per_user' => null,
                'max_allowed_participants_per_activity' => null,
                'accumulative_activities' => false,
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $copy = Event::query()->where('name', 'Owner Weekend (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame($owner->id, $copy->created_by);
        $this->assertSame($series->id, $copy->event_series_id);
    }
}
