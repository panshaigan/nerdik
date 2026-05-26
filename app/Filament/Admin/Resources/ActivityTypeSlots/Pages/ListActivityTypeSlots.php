<?php

namespace App\Filament\Admin\Resources\ActivityTypeSlots\Pages;

use App\Filament\Admin\Resources\ActivityTypeSlots\ActivityTypeSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityTypeSlots extends ListRecords
{
    protected static string $resource = ActivityTypeSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
