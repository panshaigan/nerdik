<?php

namespace App\Filament\Admin\Resources\Tags\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use App\Models\TagCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                Section::make(__('ui.activities.suggested_bring.admin_section'))
                    ->description(__('ui.activities.suggested_bring.admin_section_help'))
                    ->schema([
                        Repeater::make('suggested_bring_labels')
                            ->label(__('ui.tags.suggested_bring.heading'))
                            ->schema([
                                TextInput::make('label_en')
                                    ->label(__('ui.tags.suggested_bring.label_en'))
                                    ->maxLength(255),
                                TextInput::make('label_pl')
                                    ->label(__('ui.tags.suggested_bring.label_pl'))
                                    ->maxLength(255),
                            ])
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->visible(function (Get $get): bool {
                        $categoryId = (int) ($get('tag_category_id') ?? 0);
                        $gameCategoryId = TagCategory::idByKey(TagCategory::KEY_GAME);

                        return $gameCategoryId !== null && $categoryId === $gameCategoryId;
                    })
                    ->columnSpanFull(),
            ]);
    }
}
