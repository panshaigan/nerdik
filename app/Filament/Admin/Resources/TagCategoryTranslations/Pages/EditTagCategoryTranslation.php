<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations\Pages;

use App\Filament\Admin\Resources\TagCategoryTranslations\TagCategoryTranslationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagCategoryTranslation extends EditRecord
{
    protected static string $resource = TagCategoryTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
