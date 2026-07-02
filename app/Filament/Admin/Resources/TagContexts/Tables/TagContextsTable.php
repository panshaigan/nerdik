<?php

namespace App\Filament\Admin\Resources\TagContexts\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Models\TagContext;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TagContextsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'tag.translations',
            'context',
        ])
            ->columns([
                BelongsToColumn::record('tag', searchable: true),
                TextColumn::make('context_type')
                    ->searchable(),
                BelongsToColumn::morphContext(),
            ])
            ->filters([
                SelectFilter::make('context_type')
                    ->options(fn (): array => TagContext::query()
                        ->whereNotNull('context_type')
                        ->distinct()
                        ->orderBy('context_type')
                        ->pluck('context_type', 'context_type')
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
