<?php

namespace App\Filament\Admin\Resources\TagCategoryTranslations\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Models\TagCategoryTranslation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagCategoryTranslationsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'category.translations',
        ])
            ->columns([
                BelongsToColumn::record('category', label: 'Tag category'),
                TextColumn::make('locale')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(fn (): array => TagCategoryTranslation::query()
                        ->whereNotNull('locale')
                        ->distinct()
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->all()),
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
