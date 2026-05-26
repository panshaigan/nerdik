<?php

namespace App\Filament\Admin\Resources\TagTranslations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tag_id')
                    ->relationship('tag', 'id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('label')
                    ->required(),
                TextInput::make('slug'),
            ]);
    }
}
