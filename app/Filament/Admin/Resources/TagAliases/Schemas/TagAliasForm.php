<?php

namespace App\Filament\Admin\Resources\TagAliases\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagAliasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tag('tag_id')
                    ->required(),
                TextInput::make('locale'),
                TextInput::make('alias')
                    ->required(),
            ]);
    }
}
