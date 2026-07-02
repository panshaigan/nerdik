<?php

namespace App\Filament\Admin\Resources\Places\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Filters\BelongsToFilter;
use App\Filament\Tables\Filters\CommonFilters;
use App\Models\Place;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                SelectFilter::make('type')
                    ->options([
                        Place::TYPE_VENUE => str(Place::TYPE_VENUE)->headline()->toString(),
                        Place::TYPE_ROOM => str(Place::TYPE_ROOM)->headline()->toString(),
                    ]),
                BelongsToFilter::country(),
                ...CommonFilters::auditUserFilters(),
                ...CommonFilters::auditTimestampFilters(),
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
