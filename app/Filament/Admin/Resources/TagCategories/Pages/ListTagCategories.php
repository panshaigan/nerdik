<?php

namespace App\Filament\Admin\Resources\TagCategories\Pages;

use App\Filament\Admin\Resources\TagCategories\TagCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagCategories extends ListRecords
{
    protected static string $resource = TagCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
