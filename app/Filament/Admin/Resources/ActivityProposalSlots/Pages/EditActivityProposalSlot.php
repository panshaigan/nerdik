<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots\Pages;

use App\Filament\Admin\Resources\ActivityProposalSlots\ActivityProposalSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityProposalSlot extends EditRecord
{
    protected static string $resource = ActivityProposalSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
