<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Columns\IdColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageAliasesTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'createdBy',
        ])
            ->columns([
                IdColumn::make(),
                TextColumn::make('alias')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => '/'.$state),
                TextColumn::make('target_path')
                    ->searchable()
                    ->limit(60)
                    ->url(fn ($record): string => url($record->target_path))
                    ->openUrlInNewTab(),
                BelongsToColumn::record('createdBy', label: 'Created by'),
                TextColumn::make('hits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
