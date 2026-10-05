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

    public function test_dashboard_groups_items_in_the_same_month_under_one_timeline_heading(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $sharedMonthStart = now()->addMonths(2)->setDay(12)->setTime(14, 30);

        $event = Event::factory()->create([
            'name' => 'Timeline Same Month Event',
            'created_by' => $organizer->id,
            'starts_at' => $sharedMonthStart,
            'ends_at' => $sharedMonthStart->copy()->addHours(2),
        ]);

        $activity = Activity::factory()->create([
            'name' => 'Timeline Same Month Activity',
            'created_by' => $organizer->id,
            'starts_at' => $sharedMonthStart->copy()->addDays(5)->setTime(10, 0),
            'ends_at' => $sharedMonthStart->copy()->addDays(5)->addHours(3),
        ]);

        $viewer->interestedEvents()->attach($event->id);
        $viewer->interestedActivities()->attach($activity->id);

        $expectedLabel = format_datetime_in_user_tz($sharedMonthStart, 'MMMM YYYY');

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee($expectedLabel)
            ->assertSee('Timeline Same Month Event')
            ->assertSee('Timeline Same Month Activity')
            ->assertSeeHtml('data-ui="dashboard-feed-group-'.$sharedMonthStart->getTimestamp().'"')
            ->assertSeeHtml('ui-dashboard-feed-collapse')
            ->assertSeeHtml('ui-dashboard-feed-collapse-content')
            ->assertSeeHtml('ui-dashboard-feed-listings');
    }

    public function test_dashboard_shows_separate_timeline_headings_for_different_months(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $juneStart = now()->addMonths(3)->setMonth(6)->setDay(10)->setTime(10, 0);
        $julyStart = now()->addMonths(3)->setMonth(7)->setDay(15)->setTime(15, 0);

        $juneEvent = Event::factory()->create([
            'name' => 'Timeline June Event',
            'created_by' => $organizer->id,
            'starts_at' => $juneStart,
            'ends_at' => $juneStart->copy()->addHours(2),
        ]);

        $julyActivity = Activity::factory()->create([
            'name' => 'Timeline July Activity',
            'created_by' => $organizer->id,
            'starts_at' => $julyStart,
            'ends_at' => $julyStart->copy()->addHours(2),
        ]);

        $viewer->interestedEvents()->attach($juneEvent->id);
        $viewer->interestedActivities()->attach($julyActivity->id);

        $juneLabel = format_datetime_in_user_tz($juneStart, 'MMMM YYYY');
        $julyLabel = format_datetime_in_user_tz($julyStart, 'MMMM YYYY');

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee($juneLabel)
            ->assertSee($julyLabel)
            ->assertSee('Timeline June Event')
            ->assertSee('Timeline July Activity');
    }

    public function test_dashboard_paginates_month_groups_via_livewire_page(): void
    {
        $viewer = User::factory()->create();
        $base = now()->addMonths(5)->startOfMonth()->addHours(8);

        $firstPageName = 'Dashboard Page One Event';
        $secondPageName = 'Dashboard Page Two Event';

        for ($i = 0; $i < 8; $i++) {
            $startsAt = $base->copy()->addMonths($i);
            Event::factory()->create([
                'name' => $i === 0 ? $firstPageName : "Dashboard Month Group {$i}",
                'created_by' => $viewer->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
            ]);
        }

        $secondStartsAt = $base->copy()->addMonths(8);
        Event::factory()->create([
            'name' => $secondPageName,
            'created_by' => $viewer->id,
            'starts_at' => $secondStartsAt,
            'ends_at' => $secondStartsAt->copy()->addHour(),
        ]);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee($firstPageName)
            ->assertDontSee($secondPageName)
            ->assertSeeHtml('data-ui="dashboard-feed-loading"')
            ->call('gotoPage', 2)
            ->assertSee($secondPageName)
            ->assertDontSee($firstPageName);
    }
}
