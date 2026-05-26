<?php

namespace App\Filament\Admin\Resources\TagContexts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagContextForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tag_id')
                    ->relationship('tag', 'id')
                    ->required(),
                TextInput::make('context_type')
                    ->required(),
                TextInput::make('context_id')
                    ->required()
                    ->numeric(),
            ]);
    }
}
