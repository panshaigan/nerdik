<?php

namespace App\Filament\Admin\Resources\Slots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('event_id')
                    ->relationship('event', 'name')
                    ->required(),
                Select::make('activity_id')
                    ->relationship('activity', 'name'),
                Select::make('place_id')
                    ->relationship('place', 'name'),
                Toggle::make('requires_approval')
                    ->required(),
                TextInput::make('max_capacity')
                    ->numeric(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
            ]);
    }
}
