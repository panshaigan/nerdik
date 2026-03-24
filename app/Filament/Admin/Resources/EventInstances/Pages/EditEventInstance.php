<?php

namespace App\Filament\Admin\Resources\EventInstances\Pages;

use App\Filament\Admin\Resources\EventInstances\EventInstanceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEventInstance extends EditRecord
{
    protected static string $resource = EventInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
