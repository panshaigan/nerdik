<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BadgeSemantic;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BadgeSemanticTest extends TestCase
{
    #[Test]
    public function badge_classes_use_outline_variant_when_requested(): void
    {
        $classes = BadgeSemantic::Warning->badgeClasses(outline: true);

        $this->assertStringContainsString('badge-outline', $classes);
        $this->assertStringContainsString('badge-warning', $classes);
    }

    #[Test]
    public function badge_classes_render_filled_variant_without_outline_class(): void
    {
        $classes = BadgeSemantic::Warning->badgeClasses(outline: false);

        $this->assertStringNotContainsString('badge-outline', $classes);
        $this->assertStringContainsString('badge-warning', $classes);
    }
}
