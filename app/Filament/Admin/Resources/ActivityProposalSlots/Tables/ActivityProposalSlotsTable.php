<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Columns\IdColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ActivityProposalSlotsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'proposal.activity',
            'proposal.event',
            'slot.event',
        ])
            ->columns([
                IdColumn::make(),
                BelongsToColumn::record('proposal', label: 'Proposal'),
                BelongsToColumn::slot(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
