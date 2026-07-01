<?php

namespace App\Filament\Admin\Resources\CountryTranslations\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CountryTranslationsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'country.translations',
        ])
            ->columns([
                BelongsToColumn::record('country', searchable: true),
                TextColumn::make('locale')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
