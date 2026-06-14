<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Livewire\Activities\ShowActivity;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\View\Components\Ui\TabsWithToolbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowHeroToolbarOverflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function activity_show_renders_toolbar_tooltips_without_clipping_overflow_classes(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $html = Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->html();

        $this->assertStringContainsString('ui-activity-show-hero', $html);
        $this->assertStringContainsString('data-ui="activity-show-tabs-toolbar"', $html);
        $this->assertStringContainsString('data-tip="'.__('ui.activities.edit').'"', $html);
        $this->assertStringContainsString('data-tip="'.__('ui.activities.duplicate_action').'"', $html);
        $this->assertHeroDoesNotUseInlineOverflowHidden($html, 'ui-activity-show-hero');
        $this->assertTabsRootDoesNotClipOverflow($html, 'activity-show-tabs');
    }

    #[Test]
    public function event_show_renders_toolbar_tooltips_without_clipping_overflow_classes(): void
    {
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $html = Livewire::actingAs($host)
            ->test(ShowEvent::class, ['event' => $event])
            ->html();

        $this->assertStringContainsString('ui-event-show-hero', $html);
        $this->assertStringContainsString('data-ui="event-show-tabs-toolbar"', $html);
        $this->assertStringContainsString('data-tip="'.__('ui.common.edit').'"', $html);
        $this->assertStringContainsString('data-tip="'.__('ui.events.duplicate_action').'"', $html);
        $this->assertHeroDoesNotUseInlineOverflowHidden($html, 'ui-event-show-hero');
        $this->assertTabsRootDoesNotClipOverflow($html, 'event-show-tabs');
    }

    #[Test]
    public function tabs_with_toolbar_defaults_to_column_layout_without_root_overflow_scroll(): void
    {
        $component = new TabsWithToolbar(selected: 'info');

        $this->assertStringContainsString('flex-col', $component->tabsClass);

        $html = Blade::render('<x-ui.tabs-with-toolbar selected="info" />');

        $this->assertStringNotContainsString('x-class=', $html);
        $this->assertStringNotContainsString('overflow-x-auto', $this->tabsRootClassAttribute($html));
    }

    /**
     * @param  non-empty-string  $html
     * @param  non-empty-string  $heroClass
     */
    private function assertHeroDoesNotUseInlineOverflowHidden(string $html, string $heroClass): void
    {
        if (preg_match('/<div\b[^>]*\bclass="[^"]*\b'.preg_quote($heroClass, '/').'\b[^"]*"[^>]*>/', $html, $matches) !== 1) {
            $this->fail('Hero element not found in rendered HTML.');
        }

        $this->assertStringNotContainsString('overflow-hidden', $matches[0]);
    }

    /**
     * @param  non-empty-string  $html
     * @param  non-empty-string  $tabsDataUi
     */
    private function assertTabsRootDoesNotClipOverflow(string $html, string $tabsDataUi): void
    {
        if (preg_match('/<div\b[^>]*\bdata-ui="'.preg_quote($tabsDataUi, '/').'"[^>]*>/', $html, $matches) !== 1) {
            $this->fail('Tabs root element not found in rendered HTML.');
        }

        $this->assertStringNotContainsString('overflow-x-auto', $matches[0]);
        $this->assertStringNotContainsString('x-class=', $matches[0]);
    }

    /**
     * @param  non-empty-string  $html
     */
    private function tabsRootClassAttribute(string $html): string
    {
        if (preg_match('/<div\b[^>]*\bx-data="[^"]*tabs[^"]*"[^>]*>/', $html, $matches) !== 1) {
            $this->fail('Tabs root element not found in rendered HTML.');
        }

        if (preg_match('/\bclass="([^"]*)"/', $matches[0], $classMatches) !== 1) {
            return '';
        }

        return $classMatches[1];
    }
}
