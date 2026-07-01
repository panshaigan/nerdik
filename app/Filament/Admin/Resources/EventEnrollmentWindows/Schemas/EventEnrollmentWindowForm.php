<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\DateTimePicker;
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
                BelongsToSelect::make('event_id', 'event')
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
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
            ]);
    }
}
