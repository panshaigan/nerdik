<?php

namespace App\Filament\Admin\Resources\TagContexts;

use App\Filament\Admin\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Resource;
use App\Filament\Admin\Resources\TagContexts\Pages\CreateTagContext;
use App\Filament\Admin\Resources\TagContexts\Pages\EditTagContext;
use App\Filament\Admin\Resources\TagContexts\Pages\ListTagContexts;
use App\Filament\Admin\Resources\TagContexts\Schemas\TagContextForm;
use App\Filament\Admin\Resources\TagContexts\Tables\TagContextsTable;
use App\Models\TagContext;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TagContextResource extends Resource
{
    protected static ?string $model = TagContext::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Tags;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return TagContextForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagContextsTable::configure($table);
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
            'index' => ListTagContexts::route('/'),
            'create' => CreateTagContext::route('/create'),
            'edit' => EditTagContext::route('/{record}/edit'),
        ];
    }
}
