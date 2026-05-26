<?php

namespace App\Filament\Admin\Resources\CityTranslations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CityTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('city_id')
                    ->relationship('city', 'id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
