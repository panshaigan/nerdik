<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Dashboard\Dashboard;
use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostgresRuntimePortabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_activity_search_proposal_events_accepts_datetime_query_without_sql_error(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Postgres Search Event',
            'starts_at' => now()->addDays(3)->setTime(10, 30),
            'ends_at' => now()->addDays(3)->setTime(12, 0),
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->call('searchProposalEvents', $event->starts_at->format('Y-m-d H:i'));

        $this->assertTrue(true);
    }

    public function test_dashboard_renders_unified_feed_on_postgres_safe_raw_queries(): void
    {
        $user = User::factory()->create();
        Event::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(2),
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertStatus(200);
    }

    public function test_manage_event_form_reuses_existing_organization_case_insensitively(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);
        $organization = Organization::factory()->create([
            'name' => 'Alpha Guild',
        ]);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'PG Event')
            ->set('organization_id', null)
            ->set('organization_name', 'alpha guild')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Default window')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->call('save');

        $this->assertSame(1, Organization::query()->whereRaw('LOWER(name) = LOWER(?)', ['alpha guild'])->count());
        $this->assertDatabaseHas('events', [
            'name' => 'PG Event',
            'organization_id' => $organization->id,
        ]);
    }
}
