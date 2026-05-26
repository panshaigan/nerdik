<?php

namespace App\Filament\Admin\Resources\CityTranslations;

use App\Filament\Admin\Resources\CityTranslations\Pages\CreateCityTranslation;
use App\Filament\Admin\Resources\CityTranslations\Pages\EditCityTranslation;
use App\Filament\Admin\Resources\CityTranslations\Pages\ListCityTranslations;
use App\Filament\Admin\Resources\CityTranslations\Schemas\CityTranslationForm;
use App\Filament\Admin\Resources\CityTranslations\Tables\CityTranslationsTable;
use App\Models\CityTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CityTranslationResource extends Resource
{
    protected static ?string $model = CityTranslation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CityTranslationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CityTranslationsTable::configure($table);
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
            'index' => ListCityTranslations::route('/'),
            'create' => CreateCityTranslation::route('/create'),
            'edit' => EditCityTranslation::route('/{record}/edit'),
        ];
    }
}
