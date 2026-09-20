<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Services\ActivityFamiliarityService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityWaitlistEntriesTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'activity',
            'user',
        ])
            ->columns([
                TextColumn::make('activity.name')
                    ->searchable(),
                BelongsToColumn::user('user_id'),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('familiarity')
                    ->label('Familiarity')
                    ->formatStateUsing(fn ($state): string => app(ActivityFamiliarityService::class)
                        ->formatSnapshotSummary(is_array($state) ? $state : null))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
