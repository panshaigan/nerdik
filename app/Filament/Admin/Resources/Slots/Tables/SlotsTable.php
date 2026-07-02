<?php

namespace App\Filament\Admin\Resources\Slots\Tables;

use App\Enums\ParticipationMode;
use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Filters\CommonFilters;
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

class SlotsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'event',
            'activity',
            'place.parent',
            'creator',
            'updater',
            'deleter',
        ])
            ->columns([
                BelongsToColumn::slotRecord(),
                TextColumn::make('event.name')
                    ->searchable(),
                TextColumn::make('activity.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                BelongsToColumn::place(),
                IconColumn::make('requires_approval')
                    ->boolean(),
                IconColumn::make('forces_participation_settings')
                    ->boolean(),
                TextColumn::make('participation_mode')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lottery_draw_in_hours')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('allows_observers')
                    ->boolean(),
                TextColumn::make('max_capacity')
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
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                BelongsToColumn::user('created_by'),
                BelongsToColumn::user('updated_by'),
                BelongsToColumn::user('deleted_by'),
            ])
            ->filters([
                SelectFilter::make('participation_mode')
                    ->options(collect(ParticipationMode::cases())
                        ->mapWithKeys(fn (ParticipationMode $mode): array => [$mode->value => $mode->label()])
                        ->all()),
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
