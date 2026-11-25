<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases;

use App\Filament\Admin\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\PageAliases\Pages\CreatePageAlias;
use App\Filament\Admin\Resources\PageAliases\Pages\EditPageAlias;
use App\Filament\Admin\Resources\PageAliases\Pages\ListPageAliases;
use App\Filament\Admin\Resources\PageAliases\Schemas\PageAliasForm;
use App\Filament\Admin\Resources\PageAliases\Tables\PageAliasesTable;
use App\Filament\Admin\Resources\Resource;
use App\Models\PageAlias;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PageAliasResource extends Resource
{
    protected static ?string $model = PageAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Communication;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Page aliases';

    protected static ?string $modelLabel = 'page alias';

    protected static ?string $pluralModelLabel = 'page aliases';

    public static function form(Schema $schema): Schema
    {
        return PageAliasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageAliasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageAliases::route('/'),
            'create' => CreatePageAlias::route('/create'),
            'edit' => EditPageAlias::route('/{record}/edit'),
        ];
    }
}
