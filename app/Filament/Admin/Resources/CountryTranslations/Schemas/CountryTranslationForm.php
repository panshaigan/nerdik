<?php

namespace App\Filament\Admin\Resources\CountryTranslations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CountryTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->relationship('country', 'id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
