<?php

namespace App\Filament\Admin\Resources\ActivityTypes\Pages;

use App\Filament\Admin\Resources\ActivityTypes\ActivityTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityType extends EditRecord
{
    protected static string $resource = ActivityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
