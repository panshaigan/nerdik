<?php

namespace App\Filament\Admin\Resources\TagContexts\Pages;

use App\Filament\Admin\Resources\TagContexts\TagContextResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagContexts extends ListRecords
{
    protected static string $resource = TagContextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
