<?php

namespace App\Filament\Admin\Resources\Cities\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::country('country_id')
                    ->required(),
                TextInput::make('slug'),
            ]);
    }
}
