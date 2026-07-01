<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagCategoryTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tagCategory('tag_category_id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('label')
                    ->required(),
            ]);
    }
}
