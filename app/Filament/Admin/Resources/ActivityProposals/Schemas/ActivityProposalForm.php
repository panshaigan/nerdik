<?php

namespace App\Filament\Admin\Resources\ActivityProposals\Schemas;

use App\Enums\ActivityProposalStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('activity_id')
                    ->relationship('activity', 'name')
                    ->required(),
                Select::make('event_id')
                    ->relationship('event', 'name')
                    ->required(),
                Select::make('accepted_slot_id')
                    ->relationship('acceptedSlot', 'name'),
                Select::make('status')
                    ->options(ActivityProposalStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('preferred_start_time'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
            ]);
    }
}
