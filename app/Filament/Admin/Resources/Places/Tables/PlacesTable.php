<?php

namespace App\Filament\Admin\Resources\Places\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PlacesTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'parent',
            'country.translations',
            'city.translations',
            'creator',
            'updater',
            'deleter',
        ])
            ->columns([
                BelongsToColumn::placeName(),
                TextColumn::make('type')
                    ->searchable(),
                BelongsToColumn::record('country', searchable: true),
                BelongsToColumn::record('city', searchable: true),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('links')
                    ->searchable(),
                IconColumn::make('is_online')
                    ->boolean(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('logo_path')
                    ->searchable(),
                TextColumn::make('slug')
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
