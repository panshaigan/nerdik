<?php

declare(strict_types=1);

namespace App\Filament\Admin\Support;

use App\Filament\Forms\Components\TinyMceEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;

final class AdminCustomEmailForm
{
    /**
     * @return list<Component>
     */
    public static function components(bool $includePlaceholderHint = true): array
    {
        $components = [
            TextInput::make('subject')
                ->label(__('admin_custom_email.fields.subject'))
                ->helperText(__('admin_custom_email.fields.placeholders_hint'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TinyMceEditor::make('bodyHtml')
                ->label(__('admin_custom_email.fields.body'))
                ->helperText(__('admin_custom_email.fields.placeholders_hint'))
                ->required()
                ->columnSpanFull(),
        ];

        if ($includePlaceholderHint) {
            $components[] = ViewField::make('placeholder_reference')
                ->label(__('admin_custom_email.fields.placeholders'))
                ->view('filament.admin.components.admin-email-placeholders')
                ->dehydrated(false)
                ->columnSpanFull();
        }

        return $components;
    }
}
