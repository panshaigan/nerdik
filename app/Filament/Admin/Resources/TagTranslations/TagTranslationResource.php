<?php

namespace App\Filament\Admin\Resources\TagTranslations;

use App\Filament\Admin\Resources\TagTranslations\Pages\CreateTagTranslation;
use App\Filament\Admin\Resources\TagTranslations\Pages\EditTagTranslation;
use App\Filament\Admin\Resources\TagTranslations\Pages\ListTagTranslations;
use App\Filament\Admin\Resources\TagTranslations\Schemas\TagTranslationForm;
use App\Filament\Admin\Resources\TagTranslations\Tables\TagTranslationsTable;
use App\Models\TagTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagTranslationResource extends Resource
{
    protected static ?string $model = TagTranslation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TagTranslationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagTranslationsTable::configure($table);
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
            'index' => ListTagTranslations::route('/'),
            'create' => CreateTagTranslation::route('/create'),
            'edit' => EditTagTranslation::route('/{record}/edit'),
        ];
    }
}
