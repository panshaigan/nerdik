<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows\Pages;

use App\Filament\Admin\Resources\EventEnrollmentWindows\EventEnrollmentWindowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventEnrollmentWindow extends EditRecord
{
    protected static string $resource = EventEnrollmentWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
