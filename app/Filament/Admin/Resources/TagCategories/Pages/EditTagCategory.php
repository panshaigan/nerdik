<?php

namespace App\Filament\Admin\Resources\TagCategories\Pages;

use App\Filament\Admin\Resources\TagCategories\TagCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagCategory extends EditRecord
{
    protected static string $resource = TagCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
