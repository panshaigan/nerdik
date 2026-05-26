<?php

namespace App\Filament\Admin\Resources\TagRelations;

use App\Filament\Admin\Resources\TagRelations\Pages\CreateTagRelation;
use App\Filament\Admin\Resources\TagRelations\Pages\EditTagRelation;
use App\Filament\Admin\Resources\TagRelations\Pages\ListTagRelations;
use App\Filament\Admin\Resources\TagRelations\Schemas\TagRelationForm;
use App\Filament\Admin\Resources\TagRelations\Tables\TagRelationsTable;
use App\Models\TagRelation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagRelationResource extends Resource
{
    protected static ?string $model = TagRelation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TagRelationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagRelationsTable::configure($table);
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
            'index' => ListTagRelations::route('/'),
            'create' => CreateTagRelation::route('/create'),
            'edit' => EditTagRelation::route('/{record}/edit'),
        ];
    }
}
