<?php

namespace App\Filament\Admin\Resources\ActivityProposals\Pages;

use App\Filament\Admin\Resources\ActivityProposals\ActivityProposalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityProposals extends ListRecords
{
    protected static string $resource = ActivityProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
