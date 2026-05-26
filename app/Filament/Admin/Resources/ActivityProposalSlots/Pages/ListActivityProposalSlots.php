<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots\Pages;

use App\Filament\Admin\Resources\ActivityProposalSlots\ActivityProposalSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityProposalSlots extends ListRecords
{
    protected static string $resource = ActivityProposalSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
