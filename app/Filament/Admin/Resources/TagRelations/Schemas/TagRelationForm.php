<?php

namespace App\Filament\Admin\Resources\TagRelations\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Schemas\Schema;

class TagRelationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tag('tag_id')
                    ->required(),
                BelongsToSelect::tag('related_tag_id', 'relatedTag')
                    ->required(),
            ]);
    }
}
