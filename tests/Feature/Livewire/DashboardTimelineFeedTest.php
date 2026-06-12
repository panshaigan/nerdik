<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTimelineFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_groups_items_in_the_same_hour_under_one_timeline_heading(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $sharedStart = now()->addDays(2)->setTime(14, 30);

        $event = Event::factory()->create([
            'name' => 'Timeline Same Hour Event',
            'created_by' => $organizer->id,
            'starts_at' => $sharedStart,
            'ends_at' => $sharedStart->copy()->addHours(2),
        ]);

        $activity = Activity::factory()->create([
            'name' => 'Timeline Same Hour Activity',
            'created_by' => $organizer->id,
            'starts_at' => $sharedStart->copy()->addMinutes(15),
            'ends_at' => $sharedStart->copy()->addHours(3),
        ]);

        $viewer->interestedEvents()->attach($event->id);
        $viewer->interestedActivities()->attach($activity->id);

        $expectedLabel = format_datetime_in_user_tz($sharedStart, 'ddd, D MMM · HH:00');

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee($expectedLabel)
            ->assertSee('Timeline Same Hour Event')
            ->assertSee('Timeline Same Hour Activity')
            ->assertSeeHtml('data-ui="dashboard-feed-group-'.$sharedStart->getTimestamp().'"');
    }

    public function test_dashboard_shows_separate_timeline_headings_for_different_hours(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $morningStart = now()->addDays(3)->setTime(10, 0);
        $afternoonStart = now()->addDays(3)->setTime(15, 0);

        $morningEvent = Event::factory()->create([
            'name' => 'Timeline Morning Event',
            'created_by' => $organizer->id,
            'starts_at' => $morningStart,
            'ends_at' => $morningStart->copy()->addHours(2),
        ]);

        $afternoonActivity = Activity::factory()->create([
            'name' => 'Timeline Afternoon Activity',
            'created_by' => $organizer->id,
            'starts_at' => $afternoonStart,
            'ends_at' => $afternoonStart->copy()->addHours(2),
        ]);

        $viewer->interestedEvents()->attach($morningEvent->id);
        $viewer->interestedActivities()->attach($afternoonActivity->id);

        $morningLabel = format_datetime_in_user_tz($morningStart, 'ddd, D MMM · HH:00');
        $afternoonLabel = format_datetime_in_user_tz($afternoonStart, 'ddd, D MMM · HH:00');

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee($morningLabel)
            ->assertSee($afternoonLabel)
            ->assertSee('Timeline Morning Event')
            ->assertSee('Timeline Afternoon Activity');
    }
}
