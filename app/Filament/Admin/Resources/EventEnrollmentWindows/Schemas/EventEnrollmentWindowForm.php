<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventEnrollmentWindowForm
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
                TextInput::make('max_activities_per_user')
                    ->numeric(),
                Toggle::make('accumulative_activities')
                    ->required(),
                TextInput::make('max_allowed_participants_per_activity')
                    ->numeric(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
            ]);
    }
}
