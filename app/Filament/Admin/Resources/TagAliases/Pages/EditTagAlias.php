<?php

namespace App\Filament\Admin\Resources\TagAliases\Pages;

use App\Filament\Admin\Resources\TagAliases\TagAliasResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagAlias extends EditRecord
{
    protected static string $resource = TagAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
