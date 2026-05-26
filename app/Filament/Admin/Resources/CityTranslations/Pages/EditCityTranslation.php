<?php

namespace App\Filament\Admin\Resources\CityTranslations\Pages;

use App\Filament\Admin\Resources\CityTranslations\CityTranslationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCityTranslation extends EditRecord
{
    protected static string $resource = CityTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
