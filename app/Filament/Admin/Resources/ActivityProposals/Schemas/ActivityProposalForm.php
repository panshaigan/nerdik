<?php

namespace App\Filament\Admin\Resources\ActivityProposals\Schemas;

use App\Enums\ActivityProposalStatus;
use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ActivityProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::make('activity_id', 'activity')
                    ->required(),
                BelongsToSelect::make('event_id', 'event')
                    ->required(),
                BelongsToSelect::make('accepted_slot_id', 'acceptedSlot'),
                Select::make('status')
                    ->options(ActivityProposalStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('preferred_start_time'),
                BelongsToSelect::user('created_by')
                    ->required(),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
