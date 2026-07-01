<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Enums\ParticipationMode;
use App\Filament\Forms\Components\BelongsToSelect;
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
                BelongsToSelect::activityType('activity_type_id'),
                TextInput::make('hosting_mode')
                    ->required()
                    ->numeric()
                    ->default(1),
                BelongsToSelect::make('place_id', 'place'),
                TextInput::make('min_participants')
                    ->numeric(),
                TextInput::make('max_participants')
                    ->numeric(),
                TextInput::make('minimum_age')
                    ->numeric(),
                TextInput::make('cancellation_deadline_in_hours')
                    ->numeric(),
                TextInput::make('lottery_draw_in_hours')
                    ->numeric(),
                TextInput::make('duration_in_minutes')
                    ->numeric(),
                Toggle::make('allows_observers')
                    ->required(),
                Toggle::make('is_host_passive')
                    ->required(),
                Select::make('participation_mode')
                    ->options(ParticipationMode::class)
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
                BelongsToSelect::make('cancelled_with_event_id', 'cancelledWithEvent'),
                DateTimePicker::make('cancelled_at'),
                BelongsToSelect::user('cancelled_by', 'canceller'),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
                TextInput::make('search_vector'),
            ]);
    }
}
