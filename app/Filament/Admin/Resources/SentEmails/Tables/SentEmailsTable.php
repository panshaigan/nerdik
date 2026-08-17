<?php

namespace App\Filament\Admin\Resources\SentEmails\Tables;

use App\Enums\SentEmailKind;
use App\Filament\Tables\Filters\BelongsToFilter;
use App\Filament\Tables\Filters\CommonFilters;
use App\Models\SentEmail;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SentEmailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['recipientUser', 'related']))
            ->columns([
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (SentEmailKind $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->description(fn (SentEmail $record): ?string => $record->recipientUser?->displayName())
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('related_type')
                    ->label('Related')
                    ->formatStateUsing(fn (mixed $state, SentEmail $record): ?string => $record->relatedLabel())
                    ->toggleable(),
                TextColumn::make('mailer')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(collect(SentEmailKind::cases())->mapWithKeys(
                        fn (SentEmailKind $kind): array => [$kind->value => $kind->label()],
                    )->all()),
                BelongsToFilter::user('recipient_user_id', 'recipientUser'),
                CommonFilters::dateRange('sent_at', 'Sent at'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('sent_at', 'desc');
    }
}
