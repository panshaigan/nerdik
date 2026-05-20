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
    }
}
