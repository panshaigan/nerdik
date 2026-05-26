<?php

namespace App\Filament\Admin\Resources\Countries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iso_alpha2')
                    ->required(),
            ]);
    }
}
