<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Filters\CommonFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventEnrollmentWindowsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'event',
            'creator',
            'updater',
        ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('event.name')
                    ->searchable(),
                TextColumn::make('max_activities_per_user')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('accumulative_activities')
                    ->boolean(),
                TextColumn::make('max_allowed_participants_per_activity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                BelongsToColumn::user('created_by'),
                BelongsToColumn::user('updated_by'),
            ])
            ->filters([
                CommonFilters::dateRange('starts_at'),
                ...CommonFilters::auditUserFilters(includeDeletedBy: false),
                ...CommonFilters::auditTimestampFilters(includeDeletedAt: false),
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
