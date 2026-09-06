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
}
