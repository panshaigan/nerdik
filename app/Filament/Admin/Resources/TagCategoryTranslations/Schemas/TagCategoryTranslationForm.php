<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagCategoryTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tag_category_id')
                    ->relationship('category', 'key')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('label')
                    ->required(),
            ]);
    }
}
