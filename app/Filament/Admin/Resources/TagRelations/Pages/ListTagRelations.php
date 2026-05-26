<?php

namespace App\Filament\Admin\Resources\TagRelations\Pages;

use App\Filament\Admin\Resources\TagRelations\TagRelationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagRelations extends ListRecords
{
    protected static string $resource = TagRelationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
