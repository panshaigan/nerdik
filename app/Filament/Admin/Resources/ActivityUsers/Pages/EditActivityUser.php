<?php

namespace App\Filament\Admin\Resources\ActivityUsers\Pages;

use App\Filament\Admin\Resources\ActivityUsers\ActivityUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityUser extends EditRecord
{
    protected static string $resource = ActivityUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
