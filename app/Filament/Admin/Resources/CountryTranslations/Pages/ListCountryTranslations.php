<?php

namespace App\Filament\Admin\Resources\CountryTranslations\Pages;

use App\Filament\Admin\Resources\CountryTranslations\CountryTranslationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCountryTranslations extends ListRecords
{
    protected static string $resource = CountryTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
