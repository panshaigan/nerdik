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

        $this->assertMatchesRegularExpression('/<svg[\s\S]*<\/svg>/', $html);
    }

    public function test_hr_defaults_color_to_base_100(): void
    {
        $html = Blade::render('<x-ui.hr text="Agenda" />');

        $this->assertStringContainsString('--hr-color: var(--color-base-100)', $html);
    }

    public function test_hr_applies_color_attribute_as_css_variable(): void
    {
        $html = Blade::render('<x-ui.hr color="neutral" text="Boundary" />');

        $this->assertStringContainsString('--hr-color: var(--color-neutral)', $html);
        $this->assertStringContainsString('Boundary', $html);
    }

    public function test_hr_falls_back_to_base_100_for_invalid_color(): void
    {
        $html = Blade::render('<x-ui.hr color="not a color!" text="Safe" />');

        $this->assertStringContainsString('--hr-color: var(--color-base-100)', $html);
        $this->assertStringNotContainsString('not a color', $html);
    }
}
