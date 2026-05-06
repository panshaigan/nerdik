<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabProposalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_plan_tab_shows_propose_ctas_when_before_event_and_outside_enrollment_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('canShowPlanActivityProposalUi', true)
            ->assertSee(__('ui.events.propose_activity'))
            ->assertSee(__('ui.events.plan_propose_hero_title'));
    }

    public function test_plan_tab_hides_propose_ctas_after_event_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSee(__('ui.events.propose_activity'))
            ->assertDontSee(__('ui.events.plan_propose_hero_title'));
    }

    public function test_plan_tab_hides_propose_ctas_during_active_enrollment_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
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

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSee(__('ui.events.propose_activity'));
    }
}
