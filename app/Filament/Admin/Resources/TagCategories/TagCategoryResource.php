<?php

namespace App\Filament\Admin\Resources\TagCategories;

use App\Filament\Admin\Resources\TagCategories\Pages\CreateTagCategory;
use App\Filament\Admin\Resources\TagCategories\Pages\EditTagCategory;
use App\Filament\Admin\Resources\TagCategories\Pages\ListTagCategories;
use App\Filament\Admin\Resources\TagCategories\Schemas\TagCategoryForm;
use App\Filament\Admin\Resources\TagCategories\Tables\TagCategoriesTable;
use App\Models\TagCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagCategoryResource extends Resource
{
    protected static ?string $model = TagCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TagCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagCategoriesTable::configure($table);
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
            'index' => ListTagCategories::route('/'),
            'create' => CreateTagCategory::route('/create'),
            'edit' => EditTagCategory::route('/{record}/edit'),
        ];
    }
}
