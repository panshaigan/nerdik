<?php

namespace App\Filament\Admin\Resources\CountryTranslations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CountryTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::country('country_id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
