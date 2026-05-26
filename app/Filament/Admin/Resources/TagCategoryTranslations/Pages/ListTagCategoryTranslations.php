<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations\Pages;

use App\Filament\Admin\Resources\TagCategoryTranslations\TagCategoryTranslationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagCategoryTranslations extends ListRecords
{
    protected static string $resource = TagCategoryTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
