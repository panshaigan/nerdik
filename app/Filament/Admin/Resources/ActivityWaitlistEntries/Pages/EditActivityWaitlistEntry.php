<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages;

use App\Filament\Admin\Resources\ActivityWaitlistEntries\ActivityWaitlistEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityWaitlistEntry extends EditRecord
{
    protected static string $resource = ActivityWaitlistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
