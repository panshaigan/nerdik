<?php

namespace App\Filament\Admin\Resources\TagAliases\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagAliasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tag_id')
                    ->relationship('tag', 'id')
                    ->required(),
                TextInput::make('locale'),
                TextInput::make('alias')
                    ->required(),
            ]);
    }
}
