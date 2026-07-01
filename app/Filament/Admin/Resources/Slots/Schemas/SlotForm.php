<?php

namespace App\Filament\Admin\Resources\Slots\Schemas;

use App\Enums\ParticipationMode;
use App\Filament\Forms\Components\BelongsToSelect;
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
                BelongsToSelect::event()
                    ->required(),
                BelongsToSelect::activity(),
                BelongsToSelect::place(),
                Toggle::make('requires_approval')
                    ->required(),
                Toggle::make('forces_participation_settings'),
                Select::make('participation_mode')
                    ->options(ParticipationMode::class),
                TextInput::make('lottery_draw_in_hours')
                    ->numeric(),
                Toggle::make('allows_observers'),
                TextInput::make('max_capacity')
                    ->numeric(),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
