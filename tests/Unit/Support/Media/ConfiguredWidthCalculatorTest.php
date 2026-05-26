<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\ConfiguredWidthCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConfiguredWidthCalculatorTest extends TestCase
{
    private ConfiguredWidthCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ConfiguredWidthCalculator;
    }

    #[Test]
    public function it_returns_only_configured_widths_not_exceeding_source_width(): void
    {
        $widths = $this->calculator->calculateWidths(0, 400, 300);

        $this->assertSame([128, 256, 384], $widths->all());
    }

    #[Test]
    public function it_includes_widths_up_to_configured_max_when_source_is_large(): void
    {
        $widths = $this->calculator->calculateWidths(0, 2000, 1500);

        $this->assertSame([128, 256, 384, 512, 768, 1024, 1536], $widths->all());
    }

    #[Test]
    public function it_calculates_from_file_path(): void
    {
        $path = base_path('tests/fixtures/tag-sample.jpg');

        $widths = $this->calculator->calculateWidthsFromFile($path);

        $this->assertNotEmpty($widths);
        $this->assertTrue($widths->every(fn (int $w): bool => $w <= 2000));
    }
}
