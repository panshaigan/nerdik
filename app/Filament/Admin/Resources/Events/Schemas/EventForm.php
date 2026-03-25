<?php

namespace App\Filament\Admin\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('is_public')
                    ->default(true),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                TextInput::make('logo_path')
                    ->default(null),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
