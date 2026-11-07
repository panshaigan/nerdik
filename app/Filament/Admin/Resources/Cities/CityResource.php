<?php

namespace App\Filament\Admin\Resources\Cities;

use App\Filament\Admin\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Cities\Pages\CreateCity;
use App\Filament\Admin\Resources\Cities\Pages\EditCity;
use App\Filament\Admin\Resources\Cities\Pages\ListCities;
use App\Filament\Admin\Resources\Cities\Schemas\CityForm;
use App\Filament\Admin\Resources\Cities\Tables\CitiesTable;
use App\Filament\Admin\Resources\Resource;
use App\Models\City;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Geography;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitiesTable::configure($table);
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
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
