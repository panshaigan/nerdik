<?php

namespace App\Filament\Admin\Resources\TagAliases\Pages;

use App\Filament\Admin\Resources\TagAliases\TagAliasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagAliases extends ListRecords
{
    protected static string $resource = TagAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
