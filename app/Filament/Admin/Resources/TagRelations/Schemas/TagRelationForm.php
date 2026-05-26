<?php

namespace App\Filament\Admin\Resources\TagRelations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class TagRelationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tag_id')
                    ->relationship('tag', 'id')
                    ->required(),
                Select::make('related_tag_id')
                    ->relationship('relatedTag', 'id')
                    ->required(),
            ]);
    }
}
