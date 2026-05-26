<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('activity_type_id')
                    ->relationship('activityType', 'id'),
                TextInput::make('hosting_mode')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('place_id')
                    ->relationship('place', 'name'),
                TextInput::make('min_participants')
                    ->numeric(),
                TextInput::make('max_participants')
                    ->numeric(),
                TextInput::make('minimum_age')
                    ->numeric(),
                TextInput::make('cancellation_deadline_in_hours')
                    ->numeric(),
                TextInput::make('duration_in_minutes')
                    ->numeric(),
                Toggle::make('allows_observers')
                    ->required(),
                Toggle::make('is_host_passive')
                    ->required(),
                Toggle::make('requires_approval')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('logo_path'),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('cancel_reason')
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                Select::make('cancelled_with_event_id')
                    ->relationship('cancelledWithEvent', 'name'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('cancelled_by')
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
                TextInput::make('search_vector'),
            ]);
    }
}
