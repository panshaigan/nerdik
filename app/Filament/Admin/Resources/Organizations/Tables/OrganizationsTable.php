<?php

namespace App\Filament\Admin\Resources\Organizations\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'creator',
            'updater',
            'deleter',
        ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('logo_path')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('acronym')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                BelongsToColumn::user('created_by'),
                BelongsToColumn::user('updated_by'),
                BelongsToColumn::user('deleted_by'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
