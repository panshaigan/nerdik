<?php

namespace App\View\Components;

use Mary\View\Components\Editor as MaryEditor;

class Editor extends MaryEditor
{
    #[\Override]
    public function setup(): string
    {
        $this->config = array_merge([
            'toolbar' => 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | align | bullist numlist | outdent indent | link blockquote removeformat | quickimage quicktable',
            'toolbar_mode' => 'wrap',
            'statusbar' => false,
            'quickbars_selection_toolbar' => false,
            'block_formats' => __('ui.editor.block_formats'),
        ], $this->config ?? []);

        return parent::setup();
    }
}
