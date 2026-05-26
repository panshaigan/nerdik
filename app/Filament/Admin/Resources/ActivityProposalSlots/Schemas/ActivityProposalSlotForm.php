<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ActivityProposalSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('activity_proposal_id')
                    ->relationship('proposal', 'id')
                    ->required(),
                Select::make('slot_id')
                    ->relationship('slot', 'name')
                    ->required(),
            ]);
    }
}
