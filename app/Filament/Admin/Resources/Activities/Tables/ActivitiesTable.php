<?php

namespace App\Filament\Admin\Resources\Activities\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Models\Activity;
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
        return BelongsToColumn::withEagerLoads($table, [
            'activityType',
            'place.parent',
            'cancelledWithEvent',
            ...BelongsToColumn::AUDIT_USER_RELATIONSHIPS,
        ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                BelongsToColumn::record('activityType', searchable: true),
                TextColumn::make('hosting_mode')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Activity::hostingModeLabel($state))
                    ->sortable(),
                BelongsToColumn::place(),
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
                TextColumn::make('participation_mode')
                    ->badge()
                    ->sortable(),
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
                BelongsToColumn::user('cancelled_by'),
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
