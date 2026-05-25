<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventTabPanelsStayMountedTest extends TestCase
{
    use RefreshDatabase;

    public function test_mounted_tabs_track_visited_panels_without_unmounting_prior_tabs(): void
    {
        $event = Event::factory()->create();

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertSet('mountedTabs', ['description'])
            ->set('tab', 'plan')
            ->assertSet('mountedTabs', ['description', 'plan']);
    }

    public function test_shell_renders_single_tab_loading_overlay_markers(): void
    {
        $event = Event::factory()->create();

        $html = Livewire::test(ShowEvent::class, ['event' => $event])->html();

        $this->assertSame(1, substr_count($html, 'data-ui="event-show-tab-loading"'));
        $this->assertStringContainsString('wire:target="tab"', $html);
        $this->assertStringNotContainsString('bg-base-100/45', $html);
        $this->assertStringNotContainsString('backdrop-blur-[2px]', $html);
    }

    public function test_plan_tab_panel_remains_in_dom_after_switching_away_and_back(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan');

        $this->assertStringContainsString('event-plan-', $component->html());

        $component->set('tab', 'description');

        $htmlAfterReturn = $component->html();

        $this->assertStringContainsString('event-plan-', $htmlAfterReturn);
        $this->assertStringContainsString('event-desc-', $htmlAfterReturn);
    }
}
