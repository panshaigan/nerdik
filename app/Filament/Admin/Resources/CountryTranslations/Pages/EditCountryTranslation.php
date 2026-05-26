<?php

namespace App\Filament\Admin\Resources\CountryTranslations\Pages;

use App\Filament\Admin\Resources\CountryTranslations\CountryTranslationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCountryTranslation extends EditRecord
{
    protected static string $resource = CountryTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
