<?php

namespace App\Filament\Admin\Resources\ActivityProposals\Pages;

use App\Filament\Admin\Resources\ActivityProposals\ActivityProposalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityProposal extends EditRecord
{
    protected static string $resource = ActivityProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
