<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class HrComponentTest extends TestCase
{
    public function test_hr_renders_center_text_when_text_prop_is_set(): void
    {
        $html = Blade::render('<x-ui.hr text="Agenda" />');

        $this->assertStringContainsString('Agenda', $html);

        $this->assertMatchesRegularExpression(
            '/min-h-8[^"]*px-3/',
            $html,
            'Center pill should use horizontal padding for text mode.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/rounded-full border[^"]*size-8/',
            $html,
            'Center should not use fixed size-8 square when showing text.',
        );
    }

    public function test_hr_escapes_center_text(): void
    {
        $html = Blade::render('<x-ui.hr :text="$label" />', [
            'label' => '<script>X</script>',
        ]);

        $this->assertStringContainsString('&lt;script&gt;X&lt;/script&gt;', $html);
    }

    public function test_hr_renders_default_icon_when_text_is_empty(): void
    {
        $html = Blade::render('<x-ui.hr />');

        $this->assertMatchesRegularExpression(
            '/\bsize-8\b/',
            $html,
            'Icon mode should retain fixed square center.',
        );

        $this->assertMatchesRegularExpression('/<svg[\s\S]*<\/svg>/', $html);
    }

    public function test_hr_renders_end_glow_when_show_end_glow_is_enabled(): void
    {
        $html = Blade::render('<x-ui.hr show-end-glow />');

        $this->assertStringContainsString('rounded-full bg-base-100', $html);
        $this->assertStringContainsString('right-[22%]', $html);
        $this->assertStringContainsString('shadow-[0_0_12px_theme(colors.neutral/.80)]', $html);
    }

    public function test_hr_applies_neutral_glow_shadow_to_lines_and_edge_icons(): void
    {
        $html = Blade::render('<x-ui.hr />');

        $this->assertStringContainsString('overflow-visible', $html);
        $this->assertStringContainsString('drop-shadow-[0_0_12px_theme(colors.neutral/.80)]', $html);
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($html, 'drop-shadow-[0_0_12px_theme(colors.neutral/.80)]'),
            'Lines, line glow, and edge icons should share the center neutral glow.',
        );
    }
}
