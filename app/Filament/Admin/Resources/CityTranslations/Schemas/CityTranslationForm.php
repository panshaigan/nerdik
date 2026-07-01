<?php

namespace App\Filament\Admin\Resources\CityTranslations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CityTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::city('city_id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
