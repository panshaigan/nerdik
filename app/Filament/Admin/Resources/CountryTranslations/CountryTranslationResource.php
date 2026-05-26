<?php

namespace App\Filament\Admin\Resources\CountryTranslations;

use App\Filament\Admin\Resources\CountryTranslations\Pages\CreateCountryTranslation;
use App\Filament\Admin\Resources\CountryTranslations\Pages\EditCountryTranslation;
use App\Filament\Admin\Resources\CountryTranslations\Pages\ListCountryTranslations;
use App\Filament\Admin\Resources\CountryTranslations\Schemas\CountryTranslationForm;
use App\Filament\Admin\Resources\CountryTranslations\Tables\CountryTranslationsTable;
use App\Models\CountryTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CountryTranslationResource extends Resource
{
    protected static ?string $model = CountryTranslation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CountryTranslationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CountryTranslationsTable::configure($table);
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
            'index' => ListCountryTranslations::route('/'),
            'create' => CreateCountryTranslation::route('/create'),
            'edit' => EditCountryTranslation::route('/{record}/edit'),
        ];
    }
}
