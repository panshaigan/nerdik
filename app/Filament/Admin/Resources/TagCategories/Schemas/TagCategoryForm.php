<?php

namespace App\Filament\Admin\Resources\TagCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
            ]);
    }
}
