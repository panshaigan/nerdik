<?php

namespace App\Filament\Admin\Resources\TagRelations\Pages;

use App\Filament\Admin\Resources\TagRelations\TagRelationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagRelation extends EditRecord
{
    protected static string $resource = TagRelationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
