<?php

namespace App\Filament\Admin\Resources\TagContexts\Pages;

use App\Filament\Admin\Resources\TagContexts\TagContextResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagContext extends EditRecord
{
    protected static string $resource = TagContextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
