<?php

namespace App\Filament\Admin\Resources\ActivityTypeSlots\Pages;

use App\Filament\Admin\Resources\ActivityTypeSlots\ActivityTypeSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityTypeSlot extends EditRecord
{
    protected static string $resource = ActivityTypeSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
