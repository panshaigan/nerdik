<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Schemas\Schema;

class ActivityProposalSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::activityProposal('activity_proposal_id')
                    ->required(),
                BelongsToSelect::make('slot_id', 'slot')
                    ->required(),
            ]);
    }
}
