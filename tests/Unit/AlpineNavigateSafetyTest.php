<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ActivityBadges\ActivityBadgeItem;
use App\Domain\ActivityBadges\ActivityBadgeKind;
use App\Enums\BadgeSemantic;
use App\View\Components\Ui\ActivityBadgeGroup;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class AlpineNavigateSafetyTest extends TestCase
{
    public function test_close_modals_on_navigate_uses_wire_get_and_set(): void
    {
        $source = (string) file_get_contents(resource_path('js/close-modals-on-navigate.js'));

        $this->assertStringContainsString('component?.$wire', $source);
        $this->assertStringContainsString('wire.get(property)', $source);
        $this->assertStringContainsString('wire.set(property, false)', $source);
        $this->assertStringNotContainsString('component.get(property)', $source);
        $this->assertStringNotContainsString('component.set(property', $source);
        $this->assertStringContainsString('Alpine.destroyTree', $source);
        $this->assertStringContainsString('removeDuringNavigate', $source);
        $this->assertStringContainsString('dialog.remove()', $source);
        $this->assertStringContainsString('closeMaryModalsInDocument();', $source);
        $this->assertStringContainsString('closeMaryModalsInDocument(document, { removeDuringNavigate: true })', $source);
    }

    public function test_modal_uses_data_is_open_for_morph_safe_bindings(): void
    {
        $source = (string) file_get_contents(app_path('View/Components/Modal.php'));

        $this->assertStringContainsString('x-bind:class="{\'modal-open !animate-none\': !!$data.isOpen}"', $source);
        $this->assertStringContainsString('x-trap="!!$data.isOpen"', $source);
        $this->assertStringContainsString('x-bind:inert="!$data.isOpen"', $source);
        $this->assertStringContainsString('syncDialog($data.isOpen)', $source);
        $this->assertStringNotContainsString(':class="{\'modal-open !animate-none\': isOpen}"', $source);
        $this->assertStringNotContainsString('x-bind:inert="!isOpen"', $source);
        $this->assertStringNotContainsString('x-trap="isOpen"', $source);
    }

    public function test_tab_guards_selected_expressions(): void
    {
        $html = Blade::render('<x-tab name="events" label="Events" />');

        $this->assertStringContainsString("typeof selected !== 'undefined'", $html);
        $this->assertStringContainsString("typeof selected !== 'undefined' && selected === 'events'", $html);
    }

    public function test_tabs_with_toolbar_guards_selected_in_tab_buttons(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.tabs-with-toolbar selected="events" label-div-class="flex gap-5 px-1">
                <x-tab name="events" label="Events" icon="o-calendar-days" />
            </x-ui.tabs-with-toolbar>
        BLADE);

        $this->assertStringContainsString("typeof selected !== 'undefined' && selected === tab.name", $html);
    }

    public function test_tabs_with_toolbar_places_tab_buttons_inside_tablist(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.tabs-with-toolbar selected="description" label-div-class="flex gap-5 px-1">
                <x-tab name="description" label="Description" icon="o-document-text" />
            </x-ui.tabs-with-toolbar>
        BLADE);

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('data-ui="tabs-panels"', $html);
        $this->assertStringContainsString('role="tab"', $html);
        $this->assertStringContainsString(':aria-selected=', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('data-tab-panel=', $html);
        $this->assertStringContainsString('syncTabAria()', $html);
        $this->assertStringContainsString('x-effect="syncTabAria()"', $html);
        $this->assertDoesNotMatchRegularExpression('/role="tablist"[^>]*>\s*<div[^>]*role="tabpanel"/', $html);
    }

    public function test_collapse_associates_heading_with_checkbox(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-collapse>
                <x-slot:heading>Section title</x-slot:heading>
                <x-slot:content>Body</x-slot:content>
            </x-collapse>
        BLADE);

        $this->assertStringContainsString('id="checkbox-', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('for="checkbox-', $html);
        $this->assertStringContainsString('collapse-title', $html);
        $this->assertStringContainsString('Section title', $html);
    }

    public function test_popover_uses_is_open_instead_of_open(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-popover>
                <x-slot:trigger><button type="button">Trigger</button></x-slot:trigger>
                <x-slot:content>Tip</x-slot:content>
            </x-popover>
        BLADE);

        $this->assertStringContainsString('isOpen', $html);
        $this->assertStringContainsString('x-show="isOpen"', $html);
        $this->assertStringNotContainsString('x-show="open"', $html);
    }

    public function test_overflow_menu_uses_is_open_instead_of_open(): void
    {
        $html = Blade::render(
            '<x-ui.overflow-menu icon="o-share" label="Share"><span>item</span></x-ui.overflow-menu>'
        );

        $this->assertStringContainsString('isOpen', $html);
        $this->assertStringContainsString(':aria-expanded="isOpen"', $html);
        $this->assertStringNotContainsString(':aria-expanded="open"', $html);
    }

    public function test_activity_badge_group_always_provides_expanded_scope(): void
    {
        config(['activity-badges.collapse_after' => 6]);

        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: [
                new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:1',
                    'One',
                    BadgeSemantic::Neutral,
                ),
            ],
        ));

        $this->assertStringContainsString('x-data="{ expanded: false }"', $html);
        $this->assertStringNotContainsString('data-ui="activity-badge-group-toggle"', $html);
    }
}
