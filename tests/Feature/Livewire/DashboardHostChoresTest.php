<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardHostChoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_participation_warning_appears_for_underfilled_hosted_activity(): void
    {
        $host = User::factory()->create();
        Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Chore Low Fill Table',
            'min_participants' => 3,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-low_participation"')
            ->assertSee('Chore Low Fill Table');
    }

    public function test_low_participation_warning_hides_when_minimum_is_met(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'min_participants' => 1,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
        ]);

        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertDontSeeHtml('data-ui="dashboard-action-low_participation"');
    }

    public function test_mark_absences_reminder_appears_after_activity_ends(): void
    {
        $host = User::factory()->create();
        $player = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'name' => 'Chore Mark Absences',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->subHours(6),
        ]);

        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $player->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($host)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-mark_absences"')
            ->assertSee('Chore Mark Absences');
    }

    public function test_follow_next_edition_surfaces_after_series_event_ends(): void
    {
        $viewer = User::factory()->create();
        $series = EventSeries::factory()->create();
        $pastStart = now()->subDays(3);
        $pastEvent = Event::factory()->create([
            'name' => 'Chore Past Edition',
            'event_series_id' => $series->id,
            'starts_at' => $pastStart,
            'ends_at' => $pastStart->copy()->addHours(4),
        ]);
        Event::factory()->create([
            'name' => 'Chore Next Edition',
            'event_series_id' => $series->id,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(4),
        ]);

        $activity = Activity::factory()->create([
            'starts_at' => $pastStart,
            'ends_at' => $pastStart->copy()->addHours(2),
        ]);
        Slot::factory()->create([
            'event_id' => $pastEvent->id,
            'activity_id' => $activity->id,
            'starts_at' => $pastStart,
            'ends_at' => $pastStart->copy()->addHours(2),
        ]);

        DB::table('activity_user')->insert([
            'activity_id' => $activity->id,
            'user_id' => $viewer->id,
            'is_absent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-action-follow_next_edition"')
            ->assertSee('Chore Next Edition');
    }
}
