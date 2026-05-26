<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations;

use App\Filament\Admin\Resources\TagCategoryTranslations\Pages\CreateTagCategoryTranslation;
use App\Filament\Admin\Resources\TagCategoryTranslations\Pages\EditTagCategoryTranslation;
use App\Filament\Admin\Resources\TagCategoryTranslations\Pages\ListTagCategoryTranslations;
use App\Filament\Admin\Resources\TagCategoryTranslations\Schemas\TagCategoryTranslationForm;
use App\Filament\Admin\Resources\TagCategoryTranslations\Tables\TagCategoryTranslationsTable;
use App\Models\TagCategoryTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagCategoryTranslationResource extends Resource
{
    protected static ?string $model = TagCategoryTranslation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TagCategoryTranslationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagCategoryTranslationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTagCategoryTranslations::route('/'),
            'create' => CreateTagCategoryTranslation::route('/create'),
            'edit' => EditTagCategoryTranslation::route('/{record}/edit'),
        ];
    }
}
