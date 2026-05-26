<?php

namespace App\Filament\Admin\Resources\TagTranslations\Pages;

use App\Filament\Admin\Resources\TagTranslations\TagTranslationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTagTranslations extends ListRecords
{
    protected static string $resource = TagTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
