<?php

namespace App\Filament\Admin\Resources\TagTranslations\Pages;

use App\Filament\Admin\Resources\TagTranslations\TagTranslationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTagTranslation extends EditRecord
{
    protected static string $resource = TagTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
