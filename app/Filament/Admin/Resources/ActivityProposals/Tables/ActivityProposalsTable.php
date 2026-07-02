<?php

namespace App\Filament\Admin\Resources\ActivityProposals\Tables;

use App\Enums\ActivityProposalStatus;
use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Filters\CommonFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ActivityProposalsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'activity',
            'event',
            'acceptedSlot.event',
            'creator',
            'updater',
            'deleter',
        ])
            ->columns([
                TextColumn::make('activity.name')
                    ->searchable(),
                TextColumn::make('event.name')
                    ->searchable(),
                BelongsToColumn::slot('acceptedSlot'),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('preferred_start_time')
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
                SelectFilter::make('status')
                    ->options(collect(ActivityProposalStatus::cases())
                        ->mapWithKeys(fn (ActivityProposalStatus $status): array => [$status->value => str($status->value)->headline()->toString()])
                        ->all()),
                CommonFilters::dateRange('preferred_start_time'),
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
