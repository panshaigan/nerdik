<?php

namespace App\Filament\Admin\Resources\Organizations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('logo_path'),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('acronym')
                    ->maxLength(5),
                Textarea::make('description')
                    ->columnSpanFull(),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
