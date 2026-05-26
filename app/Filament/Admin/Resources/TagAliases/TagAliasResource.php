<?php

namespace App\Filament\Admin\Resources\TagAliases;

use App\Filament\Admin\Resources\TagAliases\Pages\CreateTagAlias;
use App\Filament\Admin\Resources\TagAliases\Pages\EditTagAlias;
use App\Filament\Admin\Resources\TagAliases\Pages\ListTagAliases;
use App\Filament\Admin\Resources\TagAliases\Schemas\TagAliasForm;
use App\Filament\Admin\Resources\TagAliases\Tables\TagAliasesTable;
use App\Models\TagAlias;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagAliasResource extends Resource
{
    protected static ?string $model = TagAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TagAliasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagAliasesTable::configure($table);
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
            'index' => ListTagAliases::route('/'),
            'create' => CreateTagAlias::route('/create'),
            'edit' => EditTagAlias::route('/{record}/edit'),
        ];
    }
}
