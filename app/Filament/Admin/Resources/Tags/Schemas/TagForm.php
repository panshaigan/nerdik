<?php

namespace App\Filament\Admin\Resources\Tags\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tagCategory('tag_category_id', 'tagCategory'),
                Section::make('Images')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->collection('images')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->responsiveImages()
                            ->conversion('webp')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                TextInput::make('popularity_score')
                    ->required()
                    ->numeric()
                    ->default(0),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
