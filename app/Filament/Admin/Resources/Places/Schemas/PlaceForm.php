<?php

namespace App\Filament\Admin\Resources\Places\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('name')
                    ->required(),
                TextInput::make('parent_id')
                    ->numeric()
                    ->default(null),
                Select::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'city' => 'City',
                        'venue' => 'Venue',
                        'room' => 'Room',
                    ])
                    ->required(),
                TextInput::make('links')
                    ->default(null),
                Textarea::make('desc')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_online')
                    ->required(),
                TextInput::make('latitude')
                    ->numeric()
                    ->default(null),
                TextInput::make('longitude')
                    ->numeric()
                    ->default(null),
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
