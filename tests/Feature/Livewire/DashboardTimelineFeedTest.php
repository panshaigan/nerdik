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

    public function test_all_upcoming_feed_omits_listings_already_shown_in_this_week(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $thisWeekStart = now()->addDays(2)->setTime(10, 0);
        $laterStart = now()->addWeeks(3)->setTime(10, 0);

        $thisWeekActivity = Activity::factory()->create([
            'name' => 'Dedupe This Week Only',
            'created_by' => $organizer->id,
            'starts_at' => $thisWeekStart,
            'ends_at' => $thisWeekStart->copy()->addHours(2),
        ]);

        $laterActivity = Activity::factory()->create([
            'name' => 'Dedupe Later Feed Only',
            'created_by' => $organizer->id,
            'starts_at' => $laterStart,
            'ends_at' => $laterStart->copy()->addHours(2),
        ]);

        $viewer->interestedActivities()->attach([$thisWeekActivity->id, $laterActivity->id]);

        $component = Livewire::actingAs($viewer)->test(Dashboard::class);

        $component
            ->assertSeeHtml('data-ui="dashboard-this-week"')
            ->assertSee('Dedupe This Week Only');

        $laterLabel = format_datetime_in_user_tz($laterStart, 'MMMM YYYY');

        $component
            ->assertSee($laterLabel)
            ->assertSee('Dedupe Later Feed Only');

        $html = $component->html();
        $thisWeekHeadingPos = strpos($html, 'data-ui="dashboard-this-week"');
        $feedHeadingPos = strpos($html, 'dashboard-feed-group-');
        $this->assertNotFalse($thisWeekHeadingPos);
        $this->assertNotFalse($feedHeadingPos);

        $feedSegment = substr($html, $feedHeadingPos);
        $this->assertStringNotContainsString('Dedupe This Week Only', $feedSegment);
    }

    public function test_dashboard_shows_this_week_section_for_items_in_current_week(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $thisWeekStart = now()->addDays(2)->setTime(14, 0);
        $nextMonthStart = now()->addMonth()->setTime(14, 0);

        $thisWeekActivity = Activity::factory()->create([
            'name' => 'Dashboard This Week Activity',
            'created_by' => $organizer->id,
            'starts_at' => $thisWeekStart,
            'ends_at' => $thisWeekStart->copy()->addHours(2),
        ]);

        $laterActivity = Activity::factory()->create([
            'name' => 'Dashboard Later Month Activity',
            'created_by' => $organizer->id,
            'starts_at' => $nextMonthStart,
            'ends_at' => $nextMonthStart->copy()->addHours(2),
        ]);

        $viewer->interestedActivities()->attach([$thisWeekActivity->id, $laterActivity->id]);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSeeHtml('data-ui="dashboard-this-week"')
            ->assertSee('Dashboard This Week Activity')
            ->assertSee('Dashboard Later Month Activity');
    }

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
