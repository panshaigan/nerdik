<?php

namespace App\Filament\Admin\Resources\TagTranslations\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Models\TagTranslation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagTranslationsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'tag.translations',
        ])
            ->columns([
                BelongsToColumn::record('tag', searchable: true),
                TextColumn::make('locale')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(fn (): array => TagTranslation::query()
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
