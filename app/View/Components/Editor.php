<?php

namespace App\View\Components;

use Mary\View\Components\Editor as MaryEditor;

class Editor extends MaryEditor
{
    #[\Override]
    public function setup(): string
    {
        $this->config = array_merge([
            'toolbar' => 'undo redo | blocks styles | bold italic underline strikethrough | forecolor backcolor | align | bullist numlist | outdent indent | link blockquote removeformat | image table',
            'toolbar_mode' => 'wrap',
            'statusbar' => false,
            'quickbars_selection_toolbar' => false,
            'block_formats' => __('ui.editor.block_formats'),
            'image_class_list' => [
                ['title' => __('ui.editor.image_class_none'), 'value' => ''],
                ['title' => __('ui.editor.image_float_left'), 'value' => 'float-left'],
                ['title' => __('ui.editor.image_float_right'), 'value' => 'float-right'],
            ],
            'style_formats' => [
                [
                    'title' => __('ui.editor.image_float_left'),
                    'selector' => 'img',
                    'styles' => [
                        'float' => 'left',
                        'margin' => '0 1rem 0.75rem 0',
                    ],
                ],
                [
                    'title' => __('ui.editor.image_float_right'),
                    'selector' => 'img',
                    'styles' => [
                        'float' => 'right',
                        'margin' => '0 0 0.75rem 1rem',
                    ],
                ],
            ],
            'mobile' => [
                'toolbar' => 'undo redo | blocks | bold italic underline | bullist numlist | link image table',
                'toolbar_mode' => 'scrolling',
                'statusbar' => false,
                'quickbars_selection_toolbar' => false,
                'plugins' => 'advlist autolink lists link image table quickbars',
            ],
        ], $this->config ?? []);

        return parent::setup();
    }
}
