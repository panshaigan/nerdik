<?php

namespace Tests\Unit\View\Components;

use App\View\Components\Editor;
use Tests\TestCase;

final class EditorTest extends TestCase
{
    public function test_setup_includes_nerdik_tinymce_defaults(): void
    {
        $setup = (new Editor(config: []))->setup();

        $this->assertStringContainsString('blocks', $setup);
        $this->assertStringContainsString('bold', $setup);
        $this->assertStringContainsString('italic', $setup);
        $this->assertStringContainsString("'toolbar_mode':'wrap'", $setup);
        $this->assertStringContainsString("'statusbar':false", $setup);
        $this->assertStringContainsString("'quickbars_selection_toolbar':false", $setup);
        $this->assertStringContainsString(
            "'block_formats':'".__('ui.editor.block_formats')."'",
            $setup,
        );
    }

    public function test_setup_includes_mobile_toolbar_subset(): void
    {
        $setup = (new Editor(config: []))->setup();

        $this->assertStringContainsString("'mobile':", $setup);
        $this->assertStringContainsString(
            "'toolbar':'undo redo | blocks | bold italic underline | bullist numlist | link'",
            $setup,
        );
        $this->assertStringContainsString("'toolbar_mode':'scrolling'", $setup);
        $this->assertStringContainsString("'plugins':'advlist autolink lists link quickbars'", $setup);
        $this->assertStringContainsString(
            "'toolbar':'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | align | bullist numlist | outdent indent | link blockquote removeformat | quickimage quicktable'",
            $setup,
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'mobile':\{[^}]*quickimage/",
            $setup,
        );
    }

    public function test_per_instance_config_overrides_nerdik_defaults(): void
    {
        $setup = (new Editor(config: ['height' => 260, 'toolbar' => 'bold italic']))->setup();

        $this->assertStringContainsString("'height':260", $setup);
        $this->assertStringContainsString("'toolbar':'bold italic'", $setup);
        $this->assertStringNotContainsString('quickimage', $setup);
    }
}
