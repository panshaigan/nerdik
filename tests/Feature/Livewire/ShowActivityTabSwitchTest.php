<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowActivityTabSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_panels_stay_in_dom_when_switching_tabs(): void
    {
        $activity = Activity::factory()->create();

        $component = Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->assertSet('tab', 'info');

        $this->assertStringContainsString('data-ui="activity-show-tab-info"', $component->html());
        $this->assertStringContainsString('data-ui="activity-show-tab-participation"', $component->html());

        $component->set('tab', 'participation')
            ->assertSet('tab', 'participation');

        $html = $component->html();

        $this->assertStringContainsString('data-ui="activity-show-tab-info"', $html);
        $this->assertStringContainsString('data-ui="activity-show-tab-participation"', $html);
        $this->assertStringContainsString('data-preserve-scroll', $html);
    }

    public function test_invalid_tab_normalizes_to_info(): void
    {
        $activity = Activity::factory()->create();

        Livewire::test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'not-a-real-tab')
            ->assertSet('tab', 'info');
    }
}
