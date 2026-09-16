<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowMapTab;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanMapLayoutTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_plan_tab_shows_description_and_enrollment_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $event = Event::factory()->public()->create([
            'description' => '<p>Unique plan description copy for layout test.</p>',
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Early bird',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-09 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        $html = Livewire::withoutLazyLoading()
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('data-ui="event-show-plan-description"')
            ->assertSeeHtml('data-ui="event-show-plan-enrollment"')
            ->assertSeeHtml('data-ui="event-show-enrollment-window"')
            ->assertSee('Unique plan description copy for layout test.')
            ->assertSee('Early bird')
            ->assertDontSeeHtml('data-ui="event-show-map"')
            ->html();

        $this->assertStringNotContainsString('data-ui="event-show-enrollment-open"', $html);
    }

    public function test_plan_description_collapse_starts_closed_when_enrollment_is_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $event = Event::factory()->public()->create([
            'description' => '<p>Collapsed while enrollment is open.</p>',
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        $html = Livewire::withoutLazyLoading()
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('data-ui="event-show-plan-description"')
            ->assertSeeHtml('data-ui="event-show-plan-enrollment"')
            ->assertSee(__('ui.events.enrollment_window_active_badge'))
            ->html();

        $this->assertMatchesRegularExpression(
            '/data-ui="event-show-plan-description"[\s\S]*?<input(?![^>]*\bchecked\b)[^>]*type="checkbox"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-ui="event-show-plan-enrollment"[\s\S]*?<input[^>]*\bchecked\b[^>]*type="checkbox"/',
            $html,
        );
    }

    public function test_map_tab_renders_map_markup_not_description(): void
    {
        $event = Event::factory()->public()->create([
            'description' => '<p>Should not appear on map tab.</p>',
        ]);

        Livewire::withoutLazyLoading()
            ->test(EventShowMapTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('data-ui="event-show-map"')
            ->assertSeeHtml('min-height: 520px')
            ->assertDontSee('Should not appear on map tab.');
    }

    public function test_shell_exposes_plan_then_map_tabs(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'plan')
            ->assertSeeHtml('data-ui="event-show-tab-plan"')
            ->assertSeeHtml('data-ui="event-show-tab-map"')
            ->assertDontSeeHtml('data-ui="event-show-tab-description"');
    }
}
