<?php

namespace App\Filament\Admin\Resources\Events\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('desc')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('organization_id')
                    ->numeric()
                    ->default(null),
                Toggle::make('is_public')
                    ->required(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                TextInput::make('logo_path')
                    ->default(null),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('deleted_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('updated_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
