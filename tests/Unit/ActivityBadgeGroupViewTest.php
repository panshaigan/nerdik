<?php

namespace Tests\Unit;

use App\Domain\ActivityBadges\ActivityBadgeItem;
use App\Domain\ActivityBadges\ActivityBadgeKind;
use App\Enums\BadgeSemantic;
use App\View\Components\Ui\ActivityBadgeGroup;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class ActivityBadgeGroupViewTest extends TestCase
{
    public function test_badge_label_renders_ampersand_without_double_encoding(): void
    {
        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: [
                new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:1',
                    'Sword & Sorcery',
                    BadgeSemantic::Info,
                    'o-book-open',
                    false,
                    true,
                    'browse-card-tag',
                    'Genre',
                ),
            ],
            dataUi: 'browse-card-tags',
        ));

        $this->assertStringContainsString('Sword &amp; Sorcery', $html);
        $this->assertStringNotContainsString('Sword &amp;amp; Sorcery', $html);
        $this->assertStringContainsString('data-tip="Genre"', $html);
        $this->assertStringContainsString('ui-activity-badge-tags', $html);
        $this->assertStringContainsString('tooltip tooltip-primary ui-activity-badge-tooltip', $html);
    }

    public function test_outline_badge_renders_outline_class(): void
    {
        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: [
                new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:1',
                    'Blades in the Dark',
                    BadgeSemantic::Neutral,
                    'o-puzzle-piece',
                    true,
                ),
            ],
        ));

        $this->assertStringContainsString('badge-outline', $html);
    }

    public function test_filled_badge_renders_without_outline_class(): void
    {
        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: [
                new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:2',
                    'Mental Illness',
                    BadgeSemantic::Warning,
                    'o-exclamation-triangle',
                    false,
                ),
            ],
        ));

        $this->assertStringContainsString('badge-warning', $html);
        $this->assertStringNotContainsString('badge-outline', $html);
    }

    public function test_badge_group_collapses_when_items_exceed_limit(): void
    {
        config(['activity-badges.collapse_after' => 3]);

        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = new ActivityBadgeItem(
                ActivityBadgeKind::TaxonomyTag,
                "tag:{$i}",
                "Tag {$i}",
                BadgeSemantic::Neutral,
                'o-tag',
            );
        }

        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: $items,
            dataUi: 'activity-show-badge-group',
        ));

        $this->assertStringContainsString('data-ui="activity-badge-group-toggle"', $html);
        $this->assertStringContainsString(__('ui.common.show_more'), $html);
        $this->assertStringContainsString(__('ui.common.show_less'), $html);
        $this->assertStringContainsString('x-data="{ expanded: false }"', $html);
        $this->assertStringContainsString('x-show="expanded"', $html);
    }

    public function test_badge_group_does_not_collapse_when_within_limit(): void
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
                new ActivityBadgeItem(
                    ActivityBadgeKind::TaxonomyTag,
                    'tag:2',
                    'Two',
                    BadgeSemantic::Neutral,
                ),
            ],
        ));

        $this->assertStringNotContainsString('data-ui="activity-badge-group-toggle"', $html);
        $this->assertStringNotContainsString(__('ui.common.show_more'), $html);
    }

    public function test_badge_group_collapse_can_be_disabled_via_prop(): void
    {
        config(['activity-badges.collapse_after' => 2]);

        $items = [];
        for ($i = 1; $i <= 4; $i++) {
            $items[] = new ActivityBadgeItem(
                ActivityBadgeKind::TaxonomyTag,
                "tag:{$i}",
                "Tag {$i}",
                BadgeSemantic::Neutral,
            );
        }

        $html = Blade::renderComponent(new ActivityBadgeGroup(
            items: $items,
            collapseAfter: 0,
        ));

        $this->assertStringNotContainsString('data-ui="activity-badge-group-toggle"', $html);
    }
}
