<?php

namespace App\Filament\Admin\Resources\TagTranslations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagTranslationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tag('tag_id')
                    ->required(),
                TextInput::make('locale')
                    ->required(),
                TextInput::make('label')
                    ->required(),
                TextInput::make('slug'),
            ]);
    }
}
