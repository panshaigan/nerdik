<?php

namespace App\Filament\Admin\Resources\Activities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('activityType.id')
                    ->searchable(),
                TextColumn::make('hosting_mode')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('place.name')
                    ->searchable(),
                TextColumn::make('min_participants')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_participants')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('minimum_age')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cancellation_deadline_in_hours')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('duration_in_minutes')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('allows_observers')
                    ->boolean(),
                IconColumn::make('is_host_passive')
                    ->boolean(),
                IconColumn::make('requires_approval')
                    ->boolean(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                TextColumn::make('logo_path')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('cancelledWithEvent.name')
                    ->searchable(),
                TextColumn::make('cancelled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('cancelled_by')
                    ->numeric()
                    ->sortable(),
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
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deleted_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('search_vector'),
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
