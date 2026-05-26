<?php

namespace App\Filament\Admin\Resources\CityTranslations\Pages;

use App\Filament\Admin\Resources\CityTranslations\CityTranslationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCityTranslations extends ListRecords
{
    protected static string $resource = CityTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
