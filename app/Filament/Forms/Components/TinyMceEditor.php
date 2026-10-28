<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Support\Components\Contracts\HasEmbeddedView;

final class TinyMceEditor extends Field implements HasEmbeddedView
{
    protected string $view = 'filament.forms.components.tiny-mce-editor';

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnSpanFull();
    }

    public function toEmbeddedHtml(): string
    {
        return view($this->view, [
            'field' => $this,
        ])->render();
    }
}
